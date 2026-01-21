<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Poker Pro - J Edition</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/p5.js/1.9.0/p5.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet" />
    <style>
        body { margin:0; padding:0; overflow:hidden; background:#073870; font-family: 'Segoe UI', sans-serif; }
        #p5-zone { position: fixed; top: 0; left: 0; width: 100%; height: 100vh; transition: height 0.4s cubic-bezier(0.4, 0, 0.2, 1); z-index: 0; }
        #ui-zone { position: fixed; bottom: -35vh; left: 0; width: 100%; height: 35vh; background: rgba(0, 0, 0, 0.95); border-top: 2px solid #FFD700; z-index: 10; padding: 15px; color: white; transition: bottom 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        #toggle-menu { position: absolute; top: -35px; left: 50%; transform: translateX(-50%); width: 80px; height: 35px; background: rgba(0, 0, 0, 0.95); border: 2px solid #FFD700; border-bottom: none; border-radius: 12px 12px 0 0; color: #FFD700; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 11; font-size: 1.2rem; }
        body.menu-open #ui-zone { bottom: 0; }
        body.menu-open #p5-zone { height: 65vh; }
        button.p5-btn { background: #FFD700; color: #000; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; box-shadow: 0 4px 10px rgba(0,0,0,0.3); }
        #myInfo { position: absolute; top: 20px; right: 20px; background: rgba(0, 0, 0, 0.8); border: 2px solid #FFD700; border-radius: 8px; padding: 10px; color: white; min-width: 150px; display: none; z-index: 100; }
        .nav-tabs .nav-link { color: #aaa; border: none; }
        .nav-tabs .nav-link.active { background: #FFD700 !important; color: black !important; font-weight: bold; }
        .card-img-ui { height: 100px; margin: 5px; border-radius: 5px; border: 1px solid #FFD700; background: #222; }

        #restart-overlay {
            position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
            z-index: 1000; display: none; text-align: center;
            background: rgba(0,0,0,0.9); padding: 30px; border: 3px solid #FFD700; border-radius: 15px;
        }
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
    <div id="toggle-menu" onclick="toggleMenu()">
        <span id="arrow-icon">▲</span>
    </div>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6">
                <ul class="nav nav-tabs mb-2">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#cards">Ma Main</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#board">Tapis</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#stats">Stats</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#chat">Chat</button></li>
                </ul>
                <div class="tab-content border bg-dark p-2" style="height:140px; color: white; overflow-y: auto;">
                    <div class="tab-pane fade show active" id="cards">En attente de vos cartes...</div>
                    <div class="tab-pane fade" id="board">Aucune carte sur le tapis.</div>
                    <div class="tab-pane fade" id="stats">Statistiques de la session...</div>
                    <div class="container py-5 tab-pane fade" id="chat">

                        <div class="chat-two">
                            <div class="chat-header">
                                <div>💬 Chat 1–1</div>
                                <div class="who">
                                    <label>
                                        <input type="radio" name="who" value="me" checked> Moi
                                    </label>
                                    <label>
                                        <input type="radio" name="who" value="other"> Lui/Elle
                                    </label>
                                </div>
                            </div>

                            <div class="chat-thread" id="chatThread">
                                <!-- Exemples initiaux -->
                                <div class="msg other">
                                    <div class="bubble">
                                        Salut ! On commence la partie ?
                                        <div class="meta">Lui • 12:03</div>
                                    </div>
                                </div>
                                <div class="msg me">
                                    <div class="bubble">
                                        Yes, deal !
                                        <div class="meta">Moi • 12:04</div>
                                    </div>
                                </div>
                            </div>

                            <div class="chat-compose">
                                <input id="chatInput" type="text" placeholder="Écris ton message..." />
                                <button id="chatSend">Envoyer</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="d-flex gap-2 mb-3">
                    <button class="btn btn-outline-warning fw-bold flex-grow-1">SUIVRE</button>
                    <button class="btn btn-outline-warning fw-bold flex-grow-1">MISER</button>
                    <button class="btn btn-danger fw-bold flex-grow-1">SE COUCHER</button>
                </div>
                <div class="row g-2">
                    <div class="col-3"><button class="btn btn-dark border-secondary w-100">X2</button></div>
                    <div class="col-3"><button class="btn btn-dark border-secondary w-100">POT</button></div>
                    <div class="col-3"><button class="btn btn-warning w-100 fw-bold text-dark">ALL-IN</button></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let isMenuOpen = false, imgPlayer, buttons = [], logoutBtn, playerData = [];
    const nPlayers = 2, avatarW = 80, avatarH = 100, tableW = 800, tableH = 350;
    let gameStarted = false, currentStatus = "waiting", amISeated = false, timer = 0, currentTurn = 0;
    let cardImages = {}, myHand = [], communityCards = [];

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
        myHand = []; communityCards = [];
        for(let i=0; i<nPlayers; i++) playerData[i] = {name:"", chips:0, active:false, isMe:false, hasCards:false};
        document.getElementById('myInfo').style.display = 'none';
        document.getElementById('cards').innerText = "En attente...";
        document.getElementById('board').innerText = "Aucune carte sur le tapis.";
        document.getElementById('restart-overlay').style.display = 'none';
    }

    function initButtons(){
        buttons.forEach(b => b.remove());
        buttons = [];
        if(amISeated) return;

        let currentH = document.getElementById('p5-zone').offsetHeight;
        let cx = width/2, cy = currentH / 2;
        let rx = tableW*0.52, ry = tableH*0.55;

        for(let i=0; i<nPlayers; i++){
            if(playerData[i] && playerData[i].active) continue;
            let angle = -Math.PI/2 + i*Math.PI;
            let x = cx + rx*Math.cos(angle) - avatarW/2;
            let y = cy + ry*Math.sin(angle) - avatarH/2;

            let btn = createButton("Rejoindre");
            btn.addClass('p5-btn'); btn.size(100,30);
            btn.position(x+avatarW/2-50, y+avatarH+10);
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
            const data = await res.json();

            if (data.status === 'waiting' && currentStatus !== 'waiting') resetGameState();

            currentStatus = data.status;
            gameStarted = (['pre-flop', 'flop', 'turn', 'river', 'finished'].includes(data.status));
            timer = data.timer || 0;
            currentTurn = data.currentTurn;
            communityCards = data.community_cards || [];

            document.getElementById('restart-overlay').style.display = (currentStatus === 'finished') ? 'block' : 'none';

            let playersInServer = data.players || [];
            let oldSeatedStatus = amISeated;
            amISeated = false;

            for(let i=0; i<nPlayers; i++) playerData[i].active = false;

            playersInServer.forEach((p, i) => {
                if(i < nPlayers){
                    playerData[i] = { name: p.name, chips: p.chips, active: true, isMe: p.is_me, hasCards: p.has_cards };
                    if(p.is_me) {
                        amISeated = true;
                        myHand = p.hand || [];
                        updateUI(p);
                    }
                }
            });

            // Mise à jour de l'onglet "Tapis"
            const boardTab = document.getElementById('board');
            if (communityCards.length > 0) {
                boardTab.innerHTML = communityCards.map(card => `<img src="/img/cards/${card}" class="card-img-ui">`).join('');
            } else {
                boardTab.innerText = "Le tapis est vide.";
            }

            if(oldSeatedStatus !== amISeated) initButtons();
            if(logoutBtn) amISeated ? logoutBtn.show() : logoutBtn.hide();
        } catch(e) { console.error("Sync Error:", e); }
    }

    function updateUI(p) {
        document.getElementById('myInfo').style.display = 'block';
        document.getElementById('myName').innerText = p.name || "-";
        document.getElementById('myChips').innerText = (p.chips || 0) + " J";

        const cardsTab = document.getElementById('cards');
        if (Array.isArray(myHand) && myHand.length > 0) {
            cardsTab.innerHTML = myHand.map(card => `<img src="/img/cards/${card}" class="card-img-ui">`).join('');
        } else {
            cardsTab.innerText = "En attente de la distribution...";
        }
    }

    async function joinPlayer(index){
        let name = prompt("Entrez votre nom :");
        if(!name) return;
        await fetch("/join",{
            method:"POST",
            headers:{"Content-Type":"application/json","X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content},
            body: JSON.stringify({name})
        });
        await loadPlayers();
        initButtons();
    }

    async function restartGame() {
        await fetch("/restart", {
            method: "POST",
            headers: { "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content }
        });
        location.reload();
    }

    function createLogoutButton(){
        logoutBtn = createButton("Quitter");
        logoutBtn.addClass('p5-btn'); logoutBtn.position(20, 20);
        logoutBtn.size(100,30); logoutBtn.style('background', '#ff4444'); logoutBtn.style('color', 'white');
        logoutBtn.hide();
        logoutBtn.mousePressed(async ()=>{
            await fetch("/logout",{method:"POST",headers:{"X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content}});
            location.reload();
        });
    }

    function draw(){
        clear(); background("#073870");
        let currentH = document.getElementById('p5-zone').offsetHeight;
        let cx = width/2, cy = currentH / 2;

        // TABLE
        push(); stroke("#3e2003"); strokeWeight(8); fill("#b45f06");
        ellipse(cx,cy,tableW+40,tableH+40);
        fill("#1b5e20"); stroke("#144417"); strokeWeight(4);
        ellipse(cx,cy,tableW,tableH); pop();

        // --- CARTES COMMUNES (CANVAS) ---
        let cw = 50, ch = 70;
        if (communityCards.length > 0) {
            let gap = 10;
            let totalW = communityCards.length * (cw + gap) - gap;
            let startX = cx - totalW / 2;
            for (let j = 0; j < communityCards.length; j++) {
                image(getCardImg(communityCards[j]), startX + j * (cw + gap), cy - ch/2, cw, ch);
            }
        }

        // --- POT ---
        textAlign(CENTER);
        fill("#FFD700"); textSize(18); textStyle(BOLD);
        text("POT: 0 J", cx, cy + ch/2 + 25);

        let rx = tableW*0.52, ry = tableH*0.55;
        for(let i=0; i<nPlayers; i++){
            let angle = -Math.PI/2 + i*Math.PI;
            let x = cx + rx*Math.cos(angle) - avatarW/2;
            let y = cy + ry*Math.sin(angle) - avatarH/2;

            if(playerData[i] && playerData[i].active){
                if(imgPlayer) image(imgPlayer, x, y, avatarW, avatarH);

                let isHisTurn = (gameStarted && i === currentTurn && currentStatus !== 'finished');
                if(isHisTurn){
                    push(); noFill(); stroke(255, 215, 0, 150 + sin(frameCount*0.1)*50);
                    strokeWeight(6); rect(x-5, y-5, avatarW+10, avatarH+10, 15); pop();
                }

                // --- CARTES DES JOUEURS ---
                if ((gameStarted || currentStatus === "dealing") && playerData[i].hasCards) {
                    let cardW = 40, cardH = 55;
                    let cardX = x + avatarW/2 - cardW;
                    let cardY = (y < cy) ? y + avatarH + 10 : y - cardH - 10;

                    if (playerData[i].isMe && myHand.length === 2) {
                        image(getCardImg(myHand[0]), cardX, cardY, cardW, cardH);
                        image(getCardImg(myHand[1]), cardX + cardW + 5, cardY, cardW, cardH);
                    } else {
                        image(cardImages['Dos.png'], cardX, cardY, cardW, cardH);
                        image(cardImages['Dos.png'], cardX + cardW + 5, cardY, cardW, cardH);
                    }
                }

                // --- ÉTIQUETTES NOMS ---
                let textY = (y < cy) ? y - 55 : y + avatarH + 5;
                push();
                fill(playerData[i].isMe ? "rgba(0, 80, 180, 0.9)" : "rgba(0,0,0,0.8)");
                if(isHisTurn) fill("#FFD700");
                rect(x-15, textY, avatarW+30, 45, 8);

                fill(isHisTurn ? 0 : 255); textSize(12); textStyle(BOLD);
                let displayName = playerData[i].name;
                if(displayName.length > 12) displayName = displayName.substring(0,10)+"...";
                text(displayName, x+avatarW/2, textY+20);

                fill(isHisTurn ? 0 : "#FFD700");
                text(playerData[i].chips + " J", x+avatarW/2, textY+38);
                pop();
            }
        }

        // HUD Status
        textAlign(CENTER);
        fill(255); textSize(22);
        let statusTxt = currentStatus.toUpperCase();
        if(currentStatus === 'finished') statusTxt = "PARTIE TERMINÉE";
        text(statusTxt + (timer > 0 ? " (" + timer + "s)" : ""), cx, 40);
    }

    function windowResized(){ resizeCanvas(windowWidth, windowHeight); initButtons(); }


    (function() {
        const thread = document.getElementById('chatThread');
        const input = document.getElementById('chatInput');
        const sendBtn = document.getElementById('chatSend');

        // radio "Moi" / "Lui/Elle"
        let who = 'me';
        document.querySelectorAll('input[name="who"]').forEach(r => {
            r.addEventListener('change', () => who = r.value);
        });

        // envoyer via bouton
        sendBtn.addEventListener('click', sendMessage);

        // envoyer via Enter
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        function sendMessage() {
            const text = input.value.trim();
            if (!text) return;

            const author = who === 'me' ? 'Moi' : 'Lui';
            addMessage(text, who, author);
            input.value = '';
            input.focus();

            // Auto-scroll
            thread.scrollTop = thread.scrollHeight;
        }

        function addMessage(text, side, author) {
            const row = document.createElement('div');
            row.className = `msg ${side}`;

            const bubble = document.createElement('div');
            bubble.className = 'bubble';

            const safe = text.replaceAll('<', '&lt;').replaceAll('>', '&gt;');
            const time = new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' })
                .format(new Date());

            bubble.innerHTML = `
      <div>${safe}</div>
      <div class="meta">${author} • ${time}</div>
    `;

            row.appendChild(bubble);
            thread.appendChild(row);
        }
    })();

</script>
</body>
</html>
