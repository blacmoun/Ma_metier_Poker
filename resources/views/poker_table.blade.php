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
        .bet-slider { width: 100%; cursor: pointer; accent-color: #FFD700; }
        .bet-value-display { color: #FFD700; font-weight: bold; font-size: 1.2rem; min-width: 60px; text-align: center; }
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
            <div class="col-md-5">
                <ul class="nav nav-tabs mb-2">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#cards">Ma Main</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#board">Table</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#stats">Stats</button></li>
                </ul>
                <div class="tab-content border bg-dark p-2" style="height:140px; color: white; overflow-y: auto;">
                    <div class="tab-pane fade show active" id="cards">En attente des cartes...</div>
                    <div class="tab-pane fade" id="board">Aucune carte sur la table.</div>
                    <div class="tab-pane fade" id="stats">Statistiques de session...</div>
                </div>
            </div>

            <div class="col-md-7">
                <div class="d-flex align-items-center gap-3 mb-3 bg-secondary bg-opacity-25 p-2 rounded">
                    <span class="small fw-bold text-uppercase">Mise :</span>
                    <input type="range" id="bet-range" class="bet-slider" min="10" max="100" value="20" oninput="updateBetDisplay()">
                    <span id="bet-value" class="bet-value-display">20</span>
                    <span class="small fw-bold">J</span>
                </div>

                <div class="d-flex gap-2">
                    <button id="act-call" class="btn btn-outline-warning fw-bold flex-grow-1" onclick="handlePlay('call')">SUIVRE</button>
                    <button id="act-raise" class="btn btn-warning fw-bold flex-grow-1" onclick="handlePlay('raise')">RELANCER</button>
                    <button id="act-allin" class="btn btn-danger fw-bold flex-grow-1" onclick="handlePlay('allin')">TAPIS</button>
                    <button id="act-fold" class="btn btn-outline-danger fw-bold flex-grow-1" onclick="handlePlay('fold')">COUCHER</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let isMenuOpen = false, imgPlayer, buttons = [], logoutBtn, playerData = [];
    let previousChips = [0, 0], isAllInState = false;
    const nPlayers = 2, avatarW = 80, avatarH = 100, tableW = 850, tableH = 400;
    let gameStarted = false, currentStatus = "waiting", amISeated = false, timer = 0, currentTurn = 0, dealerIndex = 0;
    let cardImages = {}, myHand = [], communityCards = [], pot = 0;
    let chipParticles = [];

    setInterval(() => { if(timer > 0) timer--; }, 1000);

    function updateBetDisplay() {
        let val = parseInt(document.getElementById('bet-range').value);
        let max = parseInt(document.getElementById('bet-range').max);
        document.getElementById('bet-value').innerText = (val >= max && max > 0) ? "TAPIS (" + val + ")" : val;
    }

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
            setInterval(loadPlayers, 1500);
        });
    }

    function resetGameState() {
        gameStarted = false; currentStatus = "waiting"; amISeated = false;
        myHand = []; communityCards = []; pot = 0; chipParticles = [];
        for(let i=0; i<nPlayers; i++) playerData[i] = {name:"", chips:0, active:false, isMe:false, hasCards:false, currentBet: 0, hand:[], handName: null};
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
            if (currentStatus !== 'showdown') {
                playerData.forEach((p, i) => { if(p.active) previousChips[i] = p.chips; });
            }

            let stillInGame = data.players.some(p => p.is_me);
            if (amISeated && !stillInGame) {
                amISeated = false;
                resetGameState();
                initButtons();
                return;
            }
            updateGameStateLocally(data);
        } catch(e) { console.error("Sync Error:", e); }
    }

    function updateUI() {
        let me = playerData.find(p => p.isMe);
        if (me) {
            document.getElementById('myInfo').style.display = 'block';
            document.getElementById('myName').innerText = me.name;
            document.getElementById('myChips').innerText = me.chips + " J";
            document.getElementById('cards').innerHTML = (myHand && myHand.length > 0) ?
                myHand.map(card => `<img src="/img/cards/${card}" class="card-img-ui">`).join('') : "Attente...";
            document.getElementById('board').innerHTML = communityCards.length > 0 ?
                communityCards.map(card => `<img src="/img/cards/${card}" class="card-img-ui">`).join('') : "Vide.";
        }
    }

    async function handlePlay(action) {
        let amount = 0;
        let betRange = document.getElementById('bet-range');
        if(action === 'raise') amount = betRange.value;
        if(action === 'allin') amount = betRange.max;

        if(['raise', 'allin'].includes(action)) spawnChips(currentTurn);

        const buttonsToDisable = ['act-call', 'act-raise', 'act-fold', 'act-allin'];
        buttonsToDisable.forEach(id => { let el = document.getElementById(id); if(el) el.disabled = true; });

        try {
            const response = await fetch("/action", {
                method: "POST",
                headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ action: action, amount: amount })
            });
            const data = await response.json();
            if (response.ok) updateGameStateLocally(data);
            else loadPlayers();
        } catch (e) { console.error(e); }
    }

    function updateGameStateLocally(data) {
        currentStatus = data.status;
        isAllInState = data.is_all_in;
        gameStarted = (['pre-flop', 'flop', 'turn', 'river', 'showdown'].includes(data.status));
        timer = data.timer;
        currentTurn = data.currentTurn;
        communityCards = data.community_cards || [];
        pot = data.pot || 0;
        dealerIndex = data.dealerIndex;
        let myBet = 0, otherMaxBet = 0, foundMe = false, myChips = 0, isItMyTurn = false;

        data.players.forEach((p, i) => {
            if(i < nPlayers){
                playerData[i] = {
                    name: p.name, chips: p.chips, active: true, isMe: p.is_me,
                    hasCards: p.has_cards, currentBet: p.current_bet || 0,
                    hand: p.hand || [], handName: p.hand_name
                };
                if(p.is_me) {
                    foundMe = true; amISeated = true;
                    myHand = p.hand || [];
                    myBet = p.current_bet || 0;
                    myChips = p.chips;
                    // FIX: Verification directe sur l'index de la boucle
                    if(i === data.currentTurn) isItMyTurn = true;
                } else {
                    otherMaxBet = Math.max(otherMaxBet, p.current_bet || 0);
                }
            }
        });

        amISeated = foundMe;
        updateUI();

        let betRange = document.getElementById('bet-range');
        let playPhase = ['pre-flop', 'flop', 'turn', 'river'].includes(currentStatus);

        if (betRange && foundMe) {
            let diffToCall = Math.max(0, otherMaxBet - myBet);
            let minRaise = otherMaxBet > 0 ? otherMaxBet + Math.max(20, otherMaxBet) : 20;

            betRange.min = Math.min(myChips, Math.max(20, minRaise));
            betRange.max = myChips;
            if (parseInt(betRange.value) < betRange.min) betRange.value = betRange.min;
            updateBetDisplay();
        }

        let callBtn = document.getElementById('act-call');
        if(callBtn) {
            if (otherMaxBet > myBet) {
                let diff = otherMaxBet - myBet;
                callBtn.innerText = "SUIVRE " + Math.min(myChips, diff);
            } else {
                callBtn.innerText = "PAROLE";
            }
        }

        ['act-call', 'act-raise', 'act-fold', 'act-allin', 'bet-range'].forEach(id => {
            let el = document.getElementById(id);
            if(el) el.disabled = !(isItMyTurn && playPhase);
        });

        if(logoutBtn) amISeated ? logoutBtn.show() : logoutBtn.hide();
        if(!amISeated) initButtons();
    }

    function spawnChips(playerIdx) {
        let cx = width/2, cy = document.getElementById('p5-zone').offsetHeight / 2;
        let rx = tableW*0.48, ry = tableH*0.45;
        let angle = -Math.PI/2 + playerIdx * Math.PI;
        let startX = cx + rx * Math.cos(angle);
        let startY = cy + ry * Math.sin(angle);
        for(let i=0; i<8; i++) chipParticles.push({ x: startX, y: startY, tx: cx + random(-30, 30), ty: cy + 85 + random(-10, 10), t: 0, s: random(0.02, 0.05) });
    }

    function draw(){
        clear(); background("#073870");
        let cx = width/2, cy = document.getElementById('p5-zone').offsetHeight / 2;

        push(); stroke("#3e2003"); strokeWeight(8); fill("#b45f06"); ellipse(cx,cy,tableW+40,tableH+40);
        fill("#1b5e20"); stroke("#144417"); strokeWeight(4); ellipse(cx,cy,tableW,tableH); pop();

        if (communityCards.length > 0) {
            let cw = 70, ch = 100, gap = 12;
            let totalW = communityCards.length * (cw + gap) - gap;
            let startX = cx - totalW / 2;
            for (let j = 0; j < communityCards.length; j++) image(getCardImg(communityCards[j]), startX + j * (cw + gap), cy - ch/2 - 20, cw, ch);
        }

        textAlign(CENTER); fill("#FFD700"); textSize(24); textStyle(BOLD); text("POT : " + pot + " J", cx, cy + 85);

        for(let i = chipParticles.length-1; i>=0; i--) {
            let p = chipParticles[i]; p.t += p.s;
            let x = lerp(p.x, p.tx, p.t); let y = lerp(p.y, p.ty, p.t);
            fill("#FFD700"); stroke(0); strokeWeight(1); ellipse(x, y, 12, 12);
            if(p.t >= 1) chipParticles.splice(i, 1);
        }

        if (timer > 0 && currentStatus !== 'waiting') {
            let maxT = (currentStatus === 'showdown' || currentStatus === 'countdown') ? 10 : 20;
            let barW = 200; let progress = (timer / maxT) * barW;
            push(); stroke(255, 30); strokeWeight(4); line(cx - barW/2, 60, cx + barW/2, 60);
            stroke(timer < 4 ? "#ff4444" : "#FFD700"); line(cx - barW/2, 60, cx - barW/2 + progress, 60); pop();
        }

        let rx = tableW*0.48, ry = tableH*0.45;
        for(let i=0; i<nPlayers; i++){
            let angle = -Math.PI/2 + i*Math.PI;
            let x = cx + rx*Math.cos(angle); let y = cy + ry*Math.sin(angle);
            if(playerData[i] && playerData[i].active){
                if (currentStatus === 'showdown' && playerData[i].chips > previousChips[i]) {
                    push(); fill("#FFD700"); noStroke(); textAlign(CENTER); textSize(22); textStyle(BOLD);
                    let bounce = sin(frameCount * 0.1) * 10;
                    text("👑 GAGNANT 👑", x, y - avatarH/2 - 30 + bounce);
                    stroke("#FFD700"); strokeWeight(4); noFill(); ellipse(x, y, avatarW + 15, avatarH + 15); pop();
                }
                if(imgPlayer) image(imgPlayer, x-avatarW/2, y-avatarH/2, avatarW, avatarH);
                if (i === dealerIndex) {
                    push(); let dx = x - avatarW/2 - 15; let dy = y + avatarH/4;
                    stroke(0); strokeWeight(1); fill(255); ellipse(dx, dy, 25, 25);
                    fill(0); textAlign(CENTER, CENTER); textSize(14); textStyle(BOLD); text("D", dx, dy + 1); pop();
                }
                if (playerData[i].currentBet > 0) {
                    push(); let by = (i === 0) ? y + 80 : y - 80;
                    fill("rgba(0,0,0,0.7)"); stroke("#FFD700"); strokeWeight(2); rect(x - 35, by - 15, 70, 30, 15);
                    noStroke(); fill(255); textAlign(CENTER, CENTER); textSize(14); text(playerData[i].currentBet, x, by); pop();
                }
                let infoW = 120, infoH = 50, uiX = x + avatarW/2 + 10, uiY = y - infoH/2;
                let isHisTurn = (gameStarted && i === currentTurn && !['showdown', 'countdown'].includes(currentStatus));
                if(isHisTurn){
                    push(); noFill(); stroke(255, 215, 0, 150 + sin(frameCount*0.1)*100); strokeWeight(4);
                    rect(uiX-5, uiY-5, infoW + (playerData[i].hasCards ? 65 : 10), infoH + 10, 12); pop();
                }
                push(); fill(playerData[i].isMe ? "rgba(0, 80, 180, 0.95)" : "rgba(0,0,0,0.85)");
                if(isHisTurn) fill("#FFD700"); rect(uiX, uiY, infoW, infoH, 8);
                fill(isHisTurn ? 0 : 255); textAlign(CENTER); textSize(13); textStyle(BOLD); text(playerData[i].name, uiX + infoW/2, uiY + 20);
                fill(isHisTurn ? 0 : "#FFD700"); text(playerData[i].chips + " J", uiX + infoW/2, uiY + 40); pop();

                if (gameStarted && playerData[i].hasCards) {
                    let jcw = 50, jch = 70, cardX = uiX + infoW + 5, cardY = uiY - 10;
                    if (playerData[i].isMe || currentStatus === 'showdown') {
                        if(playerData[i].hand && playerData[i].hand.length >= 2) {
                            image(getCardImg(playerData[i].hand[0]), cardX, cardY, jcw, jch);
                            image(getCardImg(playerData[i].hand[1]), cardX + 22, cardY, jcw, jch);
                            if (currentStatus === 'showdown' && playerData[i].handName) {
                                push(); fill(0, 220); noStroke(); rect(cardX - 5, cardY + jch + 5, 80, 22, 5);
                                fill("#FFD700"); textAlign(CENTER, CENTER); textSize(11); textStyle(BOLD);
                                text(playerData[i].handName, cardX + 35, cardY + jch + 16); pop();
                            }
                        }
                    } else {
                        image(cardImages['Dos.png'], cardX, cardY, jcw, jch);
                        image(cardImages['Dos.png'], cardX + 22, cardY, jcw, jch);
                    }
                }
            }
        }

        fill(255); textAlign(CENTER); textSize(26); textStyle(BOLD);
        let statusText = "";
        if (currentStatus === 'waiting') statusText = "ATTENTE DE JOUEURS";
        else if (currentStatus === 'countdown') statusText = "DÉMARRAGE IMMINENT...";
        else if (currentStatus === 'showdown') statusText = "FIN DE MANCHE";
        else if (isAllInState) statusText = "RÉSULTAT EN COURS...";
        else statusText = (playerData[currentTurn]?.isMe ? "C'EST VOTRE TOUR !" : "TOUR DE " + (playerData[currentTurn]?.name || "JOUEUR"));
        text(statusText, cx, 45);
    }

    async function joinPlayer(index){
        let name = prompt("Votre nom :"); if(!name) return;
        await fetch("/join",{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content},body: JSON.stringify({name})});
        loadPlayers().then(() => initButtons());
    }
    function createLogoutButton(){
        logoutBtn = createButton("Quitter"); logoutBtn.addClass('p5-btn'); logoutBtn.position(20, 20); logoutBtn.size(80,30); logoutBtn.style('background', '#ff4444'); logoutBtn.style('color', 'white'); logoutBtn.hide();
        logoutBtn.mousePressed(async ()=>{
            await fetch("/logout",{method:"POST",headers:{"X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content}});
            amISeated = false;
            resetGameState();
            initButtons();
        });
    }
    function getCardImg(cardName) { if (!cardImages[cardName]) cardImages[cardName] = loadImage("/img/cards/" + cardName); return cardImages[cardName]; }
    function windowResized(){ resizeCanvas(windowWidth, windowHeight); initButtons(); }
</script>
</body>
</html>
