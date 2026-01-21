<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Poker Pro - J Edition</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/p5.js/1.9.0/p5.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { margin:0; padding:0; overflow:hidden; background:#073870; font-family: 'Segoe UI', sans-serif; }
        #p5-zone { position: fixed; top: 0; left: 0; width: 100%; height: 100vh; transition: height 0.4s ease; z-index: 0; }
        #ui-zone { position: fixed; bottom: -35vh; left: 0; width: 100%; height: 35vh; background: rgba(0, 0, 0, 0.95); border-top: 2px solid #FFD700; z-index: 10; padding: 15px; color: white; transition: bottom 0.4s ease; }
        #toggle-menu { position: absolute; top: -35px; left: 50%; transform: translateX(-50%); width: 80px; height: 35px; background: rgba(0, 0, 0, 0.95); border: 2px solid #FFD700; border-bottom: none; border-radius: 12px 12px 0 0; color: #FFD700; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 11; font-size: 1.2rem; }
        body.menu-open #ui-zone { bottom: 0; }
        body.menu-open #p5-zone { height: 65vh; }
        button.p5-btn { background: #FFD700; color: #000; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; box-shadow: 0 4px 10px rgba(0,0,0,0.3); }
        #myInfo { position: absolute; top: 20px; right: 20px; background: rgba(0, 0, 0, 0.8); border: 2px solid #FFD700; border-radius: 8px; padding: 10px; color: white; min-width: 150px; display: none; z-index: 100; }
        .card-img-ui { height: 100px; margin: 5px; border-radius: 5px; border: 1px solid #FFD700; background: #222; }
        #restart-overlay { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 1000; display: none; text-align: center; background: rgba(0,0,0,0.9); padding: 30px; border: 3px solid #FFD700; border-radius: 15px; }
    </style>
</head>
<body>

<div id="restart-overlay">
    <h2 style="color: #FFD700;">PARTIE TERMINÉE</h2>
    <button class="btn btn-warning btn-lg fw-bold mt-3" onclick="restartGame()">NOUVELLE PARTIE</button>
</div>

<div id="myInfo">
    <div style="color: #FFD700; font-size: 10px; text-transform: uppercase;">Joueur</div>
    <span id="myName" style="font-size: 18px; font-weight: bold; display: block;">-</span>
    <div style="color: #FFD700; font-size: 10px; text-transform: uppercase; margin-top:5px;">Solde</div>
    <span id="myChips" style="font-size: 18px; font-weight: bold; display: block;">0 J</span>
</div>

<div id="p5-zone"></div>

<div id="ui-zone">
    <div id="toggle-menu" onclick="toggleMenu()"><span id="arrow-icon">▲</span></div>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6">
                <ul class="nav nav-tabs mb-2">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#cards">Ma Main</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#board">Tapis</button></li>
                </ul>
                <div class="tab-content border bg-dark p-2" style="height:140px; color: white; overflow-y: auto;">
                    <div class="tab-pane fade show active" id="cards">En attente...</div>
                    <div class="tab-pane fade" id="board">Vide.</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="d-flex gap-2 mb-3">
                    <button class="btn btn-outline-warning fw-bold flex-grow-1">SUIVRE</button>
                    <button class="btn btn-outline-warning fw-bold flex-grow-1">MISER</button>
                    <button class="btn btn-danger fw-bold flex-grow-1">SE COUCHER</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let isMenuOpen = false, imgPlayer, buttons = [], logoutBtn, playerData = [];
    const nPlayers = 2, avatarW = 80, avatarH = 100, tableW = 850, tableH = 400;
    let gameStarted = false, currentStatus = "waiting", amISeated = false, timer = 0, currentTurn = 0;
    let cardImages = {}, myHand = [], communityCards = [], pot = 0;

    setInterval(() => { if(timer > 0) timer--; }, 1000);

    function toggleMenu() {
        isMenuOpen = !isMenuOpen;
        document.body.classList.toggle('menu-open');
        document.getElementById('arrow-icon').innerText = isMenuOpen ? '▼' : '▲';
        setTimeout(() => { initButtons(); }, 410);
    }

    function preload(){
        imgPlayer = loadImage("/img/joueur.png");
        cardImages['Dos.png'] = loadImage("/img/cards/Dos.png");
    }

    function setup(){
        let canvas = createCanvas(windowWidth, windowHeight);
        canvas.parent("p5-zone");
        resetGameState();
        createLogoutButton();
        loadPlayers().then(() => {
            initButtons();
            setInterval(loadPlayers, 2000);
        });
    }

    function resetGameState() {
        gameStarted = false; currentStatus = "waiting"; amISeated = false;
        myHand = []; communityCards = []; pot = 0;
        for(let i=0; i<nPlayers; i++) playerData[i] = {name:"", chips:0, active:false, isMe:false, hasCards:false, isDealer: false, role: "", currentBet: 0};
        document.getElementById('myInfo').style.display = 'none';
        document.getElementById('restart-overlay').style.display = 'none';
    }

    function initButtons(){
        buttons.forEach(b => b.remove()); buttons = [];
        if(amISeated) return;
        let cx = width/2, cy = (isMenuOpen ? height * 0.325 : height / 2);
        let rx = tableW*0.52, ry = tableH*0.55;
        for(let i=0; i<nPlayers; i++){
            if(playerData[i] && playerData[i].active) continue;
            let angle = -Math.PI/2 + i*Math.PI;
            let x = cx + rx*Math.cos(angle);
            let y = cy + ry*Math.sin(angle);
            let btn = createButton("Rejoindre");
            btn.addClass('p5-btn'); btn.size(100,30);
            btn.position(x-50, y+avatarH/2+10);
            btn.mousePressed(() => joinPlayer(i));
            buttons.push(btn);
        }
    }

    function getCardImg(cardName) {
        if (!cardImages[cardName]) cardImages[cardName] = loadImage("/img/cards/" + cardName);
        return cardImages[cardName];
    }

    async function loadPlayers(){
        try {
            const res = await fetch("/game");
            if (!res.ok) throw new Error("Server Error");
            const data = await res.json();
            currentStatus = data.status;
            gameStarted = (['pre-flop', 'flop', 'turn', 'river', 'finished'].includes(data.status));
            timer = data.timer || 0;
            currentTurn = data.currentTurn;
            communityCards = data.community_cards || [];
            pot = data.pot || 0;

            // Re-activation du restart overlay
            document.getElementById('restart-overlay').style.display = (currentStatus === 'finished') ? 'block' : 'none';

            let playersInServer = data.players || [];
            amISeated = false;
            playersInServer.forEach((p, i) => {
                if(i < nPlayers){
                    playerData[i] = {
                        name: p.name, chips: p.chips, active: true, isMe: p.is_me,
                        hasCards: p.has_cards, isDealer: p.is_dealer,
                        role: p.role, currentBet: p.current_bet || 0
                    };
                    if(p.is_me) { amISeated = true; myHand = p.hand || []; updateUI(p); }
                }
            });
            if(logoutBtn) amISeated ? logoutBtn.show() : logoutBtn.hide();
        } catch(e) { console.error("Sync Error:", e); }
    }

    function updateUI(p) {
        document.getElementById('myInfo').style.display = 'block';
        document.getElementById('myName').innerText = p.name;
        document.getElementById('myChips').innerText = p.chips + " J";
        const cardsTab = document.getElementById('cards');
        if (myHand.length > 0) {
            cardsTab.innerHTML = myHand.map(card => `<img src="/img/cards/${card}" class="card-img-ui">`).join('');
        }
    }

    async function joinPlayer(index){
        let name = prompt("Nom :");
        if(!name) return;
        await fetch("/join",{
            method:"POST",
            headers:{"Content-Type":"application/json","X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content},
            body: JSON.stringify({name})
        });
        location.reload();
    }

    async function restartGame() {
        await fetch("/restart", { method: "POST", headers: { "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content } });
        location.reload();
    }

    function createLogoutButton(){
        logoutBtn = createButton("Quitter");
        logoutBtn.addClass('p5-btn'); logoutBtn.position(20, 20);
        logoutBtn.size(80,30); logoutBtn.style('background', '#ff4444'); logoutBtn.style('color', 'white');
        logoutBtn.hide();
        logoutBtn.mousePressed(async ()=>{
            await fetch("/logout",{method:"POST",headers:{"X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content}});
            location.reload();
        });
    }

    function draw(){
        clear(); background("#073870");
        let cx = width/2, cy = document.getElementById('p5-zone').offsetHeight / 2;

        // TABLE
        push(); stroke("#3e2003"); strokeWeight(8); fill("#b45f06");
        ellipse(cx,cy,tableW+40,tableH+40);
        fill("#1b5e20"); stroke("#144417"); strokeWeight(4);
        ellipse(cx,cy,tableW,tableH); pop();

        // CARTES COMMUNES
        let cw = 70, ch = 100;
        if (communityCards.length > 0) {
            let gap = 12;
            let totalW = communityCards.length * (cw + gap) - gap;
            let startX = cx - totalW / 2;
            for (let j = 0; j < communityCards.length; j++) {
                image(getCardImg(communityCards[j]), startX + j * (cw + gap), cy - ch/2 - 20, cw, ch);
            }
        }

        // POT (Remonté un peu)
        textAlign(CENTER); fill("#FFD700"); textSize(24); textStyle(BOLD);
        text("POT: " + pot + " J", cx, cy + 85);

        let rx = tableW*0.48, ry = tableH*0.45;
        for(let i=0; i<nPlayers; i++){
            let angle = -Math.PI/2 + i*Math.PI;
            let x = cx + rx*Math.cos(angle);
            let y = cy + ry*Math.sin(angle);

            if(playerData[i] && playerData[i].active){
                // AVATAR
                if(imgPlayer) image(imgPlayer, x-avatarW/2, y-avatarH/2, avatarW, avatarH);

                // MISE DU JOUEUR (À GAUCHE de l'avatar maintenant)
                if(playerData[i].currentBet > 0) {
                    let betX = x - avatarW/2 - 35;
                    let betY = y;
                    fill("#FFD700"); stroke(255); strokeWeight(2);
                    ellipse(betX, betY, 42, 42);
                    fill(0); noStroke(); textSize(14); textStyle(BOLD);
                    text(playerData[i].currentBet, betX, betY + 5);
                }

                // UI JOUEUR (NOM ET CHIPS)
                let infoW = 120, infoH = 50;
                let uiX = x + avatarW/2 + 10;
                let uiY = y - infoH/2;

                // CARTES (A DROITE DU NOM, CHEVAUCHÉES)
                let jcw = 50, jch = 70;
                let cardX = uiX + infoW + 5;
                let cardY = uiY - 10;

                // HIGHLIGHT TOUR ACTUEL (Ajusté sur le bloc info + cartes)
                let isHisTurn = (gameStarted && i === currentTurn && currentStatus !== 'finished');
                if(isHisTurn){
                    push(); noFill(); stroke(255, 215, 0, 150 + sin(frameCount*0.1)*100);
                    strokeWeight(4);
                    let hlW = infoW + (playerData[i].hasCards ? 65 : 10);
                    rect(uiX-5, uiY-5, hlW, infoH + 10, 12);
                    pop();
                }

                // BLOC NOM
                push();
                fill(playerData[i].isMe ? "rgba(0, 80, 180, 0.95)" : "rgba(0,0,0,0.85)");
                if(isHisTurn) fill("#FFD700");
                rect(uiX, uiY, infoW, infoH, 8);
                fill(isHisTurn ? 0 : 255); textAlign(CENTER); textSize(13); textStyle(BOLD);
                text(playerData[i].name, uiX + infoW/2, uiY + 20);
                fill(isHisTurn ? 0 : "#FFD700");
                text(playerData[i].chips + " J", uiX + infoW/2, uiY + 40);
                pop();

                // DESSIN DES CARTES
                if ((gameStarted || currentStatus === "dealing") && playerData[i].hasCards) {
                    if (playerData[i].isMe) {
                        image(getCardImg(myHand[0]), cardX, cardY, jcw, jch);
                        image(getCardImg(myHand[1]), cardX + 22, cardY, jcw, jch);
                    } else {
                        image(cardImages['Dos.png'], cardX, cardY, jcw, jch);
                        image(cardImages['Dos.png'], cardX + 22, cardY, jcw, jch);
                    }
                }

                // BOUTON DEALER
                if(playerData[i].isDealer) {
                    fill(255); stroke(0); ellipse(x, y - avatarH/2 - 15, 22, 22);
                    fill(0); noStroke(); textSize(12); text("D", x, y - avatarH/2 - 10);
                }
            }
        }
        fill(255); textAlign(CENTER); textSize(26); textStyle(BOLD);
        text(currentStatus.toUpperCase() + (timer > 0 ? " ("+timer+"s)" : ""), cx, 45);
    }

    function windowResized(){ resizeCanvas(windowWidth, windowHeight); initButtons(); }
</script>
</body>
</html>
