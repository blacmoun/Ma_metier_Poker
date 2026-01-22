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
        button:disabled { opacity: 0.3 !important; cursor: not-allowed !important; }
    </style>
</head>
<body>

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
                    <button id="act-call" class="btn btn-outline-warning fw-bold flex-grow-1" onclick="handlePlay('check')">SUIVRE</button>
                    <button id="act-raise" class="btn btn-outline-warning fw-bold flex-grow-1" onclick="handlePlay('raise')">MISER</button>
                    <button id="act-fold" class="btn btn-danger fw-bold flex-grow-1" onclick="handlePlay('fold')">SE COUCHER</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let isMenuOpen = false, imgPlayer, buttons = [], logoutBtn, playerData = [];
    const nPlayers = 2, avatarW = 80, avatarH = 100, tableW = 850, tableH = 400;
    let gameStarted = false, currentStatus = "waiting", amISeated = false, timer = 0, currentTurn = 0, dealerIndex = 0;
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
            setInterval(loadPlayers, 1500); // Polling un peu plus rapide pour la fluidité
        });
    }

    function resetGameState() {
        gameStarted = false; currentStatus = "waiting"; amISeated = false;
        myHand = []; communityCards = []; pot = 0;
        for(let i=0; i<nPlayers; i++) playerData[i] = {name:"", chips:0, active:false, isMe:false, hasCards:false, currentBet: 0, hand:[]};
        document.getElementById('myInfo').style.display = 'none';
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

    async function loadPlayers(){
        try {
            const res = await fetch("/game");
            const data = await res.json();
            currentStatus = data.status;
            gameStarted = (['pre-flop', 'flop', 'turn', 'river', 'showdown'].includes(data.status));
            timer = data.timer || 0;
            currentTurn = data.currentTurn;
            communityCards = data.community_cards || [];
            dealerIndex = data.dealerIndex;
            pot = data.pot || 0;

            let playersInServer = data.players || [];
            amISeated = false;
            playersInServer.forEach((p, i) => {
                if(i < nPlayers){
                    playerData[i] = {
                        name: p.name, chips: p.chips, active: true, isMe: p.is_me,
                        hasCards: p.has_cards, currentBet: p.current_bet || 0,
                        hand: p.hand || []
                    };
                    if(p.is_me) { amISeated = true; myHand = p.hand || []; }
                }
            });

            updateUI();

            let isMyTurn = (playerData[currentTurn] && playerData[currentTurn].isMe);
            let playPhase = ['pre-flop', 'flop', 'turn', 'river'].includes(currentStatus);
            document.getElementById('act-call').disabled = !(isMyTurn && playPhase);
            document.getElementById('act-raise').disabled = !(isMyTurn && playPhase);
            document.getElementById('act-fold').disabled = !(isMyTurn && playPhase);

            if(logoutBtn) amISeated ? logoutBtn.show() : logoutBtn.hide();
        } catch(e) { console.error("Sync Error:", e); }
    }

    function updateUI() {
        let me = playerData.find(p => p.isMe);
        if (me) {
            document.getElementById('myInfo').style.display = 'block';
            document.getElementById('myName').innerText = me.name;
            document.getElementById('myChips').innerText = me.chips + " J";
            document.getElementById('cards').innerHTML = (myHand && myHand.length > 0) ?
                myHand.map(card => `<img src="/img/cards/${card}" class="card-img-ui">`).join('') : "En attente...";
            document.getElementById('board').innerHTML = communityCards.length > 0 ?
                communityCards.map(card => `<img src="/img/cards/${card}" class="card-img-ui">`).join('') : "Vide.";
        }
    }

    async function handlePlay(action) {
        await fetch("/play", {
            method: "POST",
            headers: {"Content-Type":"application/json", "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content},
            body: JSON.stringify({action})
        });
        loadPlayers();
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

    function getCardImg(cardName) {
        if (!cardImages[cardName]) cardImages[cardName] = loadImage("/img/cards/" + cardName);
        return cardImages[cardName];
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

        // POT
        textAlign(CENTER); fill("#FFD700"); textSize(24); textStyle(BOLD);
        text("POT: " + pot + " J", cx, cy + 85);

        // TIMER VISUEL
        if (timer > 0 && currentStatus !== 'waiting') {
            let maxT = (currentStatus === 'showdown' || currentStatus === 'countdown') ? 10 : 20;
            let barW = 200;
            let progress = (timer / maxT) * barW;
            push(); stroke(255, 30); strokeWeight(4); line(cx - barW/2, 60, cx + barW/2, 60);
            stroke(timer < 4 ? "#ff4444" : "#FFD700");
            line(cx - barW/2, 60, cx - barW/2 + progress, 60); pop();
        }

        // JOUEURS
        let rx = tableW*0.48, ry = tableH*0.45;
        for(let i=0; i<nPlayers; i++){
            let angle = -Math.PI/2 + i*Math.PI;
            let x = cx + rx*Math.cos(angle);
            let y = cy + ry*Math.sin(angle);

            if(playerData[i] && playerData[i].active){
                if(imgPlayer) image(imgPlayer, x-avatarW/2, y-avatarH/2, avatarW, avatarH);

                if ((gameStarted || currentStatus === 'countdown') && i === dealerIndex) {
                    push();
                    let dx = x - avatarW/2 - 15;
                    let dy = y + avatarH/4;
                    stroke(0); strokeWeight(1); fill(255);
                    ellipse(dx, dy, 25, 25);
                    fill(0); textAlign(CENTER, CENTER); textSize(14); textStyle(BOLD);
                    text("D", dx, dy + 1);
                    pop();
                }

                if (playerData[i].currentBet > 0) {
                    push();
                    let bx = x;
                    let by = (i === 0) ? y + avatarH/2 + 30 : y - avatarH/2 - 30;
                    fill("rgba(0,0,0,0.6)"); stroke("#FFD700"); strokeWeight(1);
                    rect(bx - 30, by - 12, 60, 24, 12);
                    noStroke(); fill(255); textAlign(CENTER, CENTER); textSize(12);
                    text(playerData[i].currentBet, bx, by);
                    pop();
                }

                let infoW = 120, infoH = 50, uiX = x + avatarW/2 + 10, uiY = y - infoH/2;
                let isHisTurn = (gameStarted && i === currentTurn && !['showdown', 'countdown'].includes(currentStatus));

                if(isHisTurn){
                    push(); noFill(); stroke(255, 215, 0, 150 + sin(frameCount*0.1)*100); strokeWeight(4);
                    rect(uiX-5, uiY-5, infoW + (playerData[i].hasCards ? 65 : 10), infoH + 10, 12); pop();
                }

                push();
                fill(playerData[i].isMe ? "rgba(0, 80, 180, 0.95)" : "rgba(0,0,0,0.85)");
                if(isHisTurn) fill("#FFD700");
                rect(uiX, uiY, infoW, infoH, 8);
                fill(isHisTurn ? 0 : 255); textAlign(CENTER); textSize(13); textStyle(BOLD);
                text(playerData[i].name, uiX + infoW/2, uiY + 20);
                fill(isHisTurn ? 0 : "#FFD700"); text(playerData[i].chips + " J", uiX + infoW/2, uiY + 40);
                pop();

                // CARTES
                if (gameStarted && playerData[i].hasCards) {
                    let jcw = 50, jch = 70, cardX = uiX + infoW + 5, cardY = uiY - 10;
                    // En phase SHOWDOWN, on affiche les cartes de tout le monde
                    if (playerData[i].isMe || currentStatus === 'showdown') {
                        if(playerData[i].hand && playerData[i].hand.length >= 2) {
                            image(getCardImg(playerData[i].hand[0]), cardX, cardY, jcw, jch);
                            image(getCardImg(playerData[i].hand[1]), cardX + 22, cardY, jcw, jch);
                        }
                    } else {
                        image(cardImages['Dos.png'], cardX, cardY, jcw, jch);
                        image(cardImages['Dos.png'], cardX + 22, cardY, jcw, jch);
                    }
                }
            }
        }

        fill(255); textAlign(CENTER); textSize(26); textStyle(BOLD);
        let statusTxt = "";
        let displayTimer = Math.ceil(timer);

        if (currentStatus === 'waiting') statusTxt = "EN ATTENTE DE JOUEURS";
        else if (currentStatus === 'countdown') { statusTxt = "DISTRIBUTION DANS :"; fill("#FFD700"); }
        else if (currentStatus === 'showdown') { statusTxt = "ATTRIBUTION DES JETONS :"; fill("#FFD700"); }
        else {
            if (playerData[currentTurn]?.isMe) { statusTxt = "À VOUS DE JOUER"; fill("#FFD700"); }
            else { statusTxt = "TOUR DE : " + (playerData[currentTurn]?.name || "..."); fill(255); }
        }

        let timerTxt = (displayTimer > 0) ? " " + displayTimer + "s" : "";
        text(statusTxt + timerTxt, cx, 45);
    }

    function windowResized(){ resizeCanvas(windowWidth, windowHeight); initButtons(); }
</script>
</body>
</html>
