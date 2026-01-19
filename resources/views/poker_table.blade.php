<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Poker Table - J</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/p5.js/1.9.0/p5.min.js"></script>
    <style>
        body { margin:0; padding:0; overflow:hidden; background:#073870; font-family: 'Segoe UI', sans-serif; }
        button {
            font-family: 'Segoe UI', sans-serif;
            background: rgba(17,17,17,0.8);
            color:#FFD700;
            border:1px solid #FFD700;
            border-radius:4px;
            font-weight:bold;
            cursor:pointer;
        }
        button:hover { background:#FFD700; color:#000; }

        /* Panneau Info Joueur Haut Droite */
        #myInfo {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.8);
            border: 2px solid #FFD700;
            border-radius: 8px;
            padding: 15px;
            color: white;
            min-width: 150px;
            display: none;
            z-index: 100;
        }
        #myInfo .label { color: #FFD700; font-size: 12px; text-transform: uppercase; }
        #myInfo .val { font-size: 20px; font-weight: bold; display: block; margin-top: 5px; }
    </style>
</head>
<body>

<div id="myInfo">
    <div class="label">Joueur</div>
    <span id="myName" class="val">-</span>
    <div class="label" style="margin-top:10px;">Solde</div>
    <span id="myChips" class="val">0 J</span>
</div>

<script>
    let imgPlayer;
    let buttons = [];
    let logoutBtn;
    let playerData = [];
    const nPlayers = 2;
    const avatarW = 100;
    const avatarH = 125;
    const tableW = 900;
    const tableH = 400;

    let gameStarted = false;
    let amISeated = false;
    let timer = 0;
    const startCountdownTime = 5;
    const turnDuration = 10;
    let timerInterval;
    let pollInterval;
    let currentTurn = 0;

    function preload(){
        imgPlayer = loadImage("/img/joueur.png");
    }

    function setup(){
        createCanvas(windowWidth, windowHeight);
        resetGameState();
        createLogoutButton();
        loadPlayers();
        pollInterval = setInterval(loadPlayers, 2000);
    }

    function resetGameState() {
        gameStarted = false;
        amISeated = false;
        timer = 0;
        currentTurn = 0;
        if(timerInterval) clearInterval(timerInterval);
        for(let i=0; i<nPlayers; i++) {
            playerData[i] = {name:"", chips:0, active:false, isMe:false};
        }
        document.getElementById('myInfo').style.display = 'none';
        initButtons();
    }

    function initButtons(){
        buttons.forEach(b=>b.remove());
        buttons=[];
        let cx = width/2, cy = height/2;
        let rx = tableW*0.52, ry = tableH*0.55;

        for(let i=0;i<nPlayers;i++){
            let angle = -Math.PI/2 + i*(2*Math.PI/nPlayers);
            let x = cx + rx*Math.cos(angle) - avatarW/2;
            let y = cy + ry*Math.sin(angle) - avatarH/2;

            let btn = createButton("Rejoindre");
            btn.size(100,30);
            btn.position(x+avatarW/2-50, y+avatarH+10);
            btn.mousePressed(()=>joinPlayer(i));
            buttons.push(btn);
        }
    }

    async function loadPlayers(){
        try{
            const res = await fetch("/game");
            const data = await res.json();
            let playersInServer = data.players || [];

            let countBefore = playerData.filter(p => p.active).length;

            amISeated = false;
            for(let i=0; i<nPlayers; i++) playerData[i].active = false;

            playersInServer.forEach((p, i) => {
                if(i < nPlayers){
                    playerData[i] = { name: p.name, chips: p.chips, active: true, isMe: p.is_me };
                    if(p.is_me) {
                        amISeated = true;
                        document.getElementById('myInfo').style.display = 'block';
                        document.getElementById('myName').innerText = p.name;
                        document.getElementById('myChips').innerText = p.chips + " J";
                    }
                }
            });

            for(let i=0; i<nPlayers; i++){
                if(playerData[i].active || amISeated) buttons[i].hide();
                else buttons[i].show();
            }

            let countAfter = playerData.filter(p => p.active).length;
            if(gameStarted && countAfter < 2) resetGameState();

            if(!gameStarted && countAfter === 2 && countBefore < 2){
                startCountdown(startCountdownTime, () => {
                    gameStarted = true;
                    startTurnTimer();
                });
            }
        }catch(e){ console.error("Update error:", e); }
    }

    async function joinPlayer(index){
        if(amISeated) return;
        let name = prompt("Entrez votre nom :");
        if(!name||name.trim()==="") return;
        try {
            const res = await fetch("/join",{
                method:"POST",
                headers:{
                    "Content-Type":"application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({name})
            });
            const data = await res.json();
            if(!res.ok) { alert(data.error); return; }
            await loadPlayers();
        } catch(e) { console.error(e); }
    }

    function createLogoutButton(){
        if(logoutBtn) logoutBtn.remove();
        logoutBtn = createButton("Quitter");
        logoutBtn.position(20, 20);
        logoutBtn.size(100,30);
        logoutBtn.mousePressed(async ()=>{
            if(pollInterval) clearInterval(pollInterval);
            try {
                await fetch("/logout",{
                    method:"POST",
                    headers:{"X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content}
                });
                resetGameState();
                setTimeout(() => { pollInterval = setInterval(loadPlayers, 2000); }, 500);
            } catch(e) {
                console.error(e);
                pollInterval = setInterval(loadPlayers, 2000);
            }
        });
    }

    function startTurnTimer(){
        currentTurn = 0;
        startCountdown(turnDuration, nextTurn);
    }

    function nextTurn(){
        if(!gameStarted) return;
        currentTurn = (currentTurn + 1) % nPlayers;
        startCountdown(turnDuration, nextTurn);
    }

    function startCountdown(seconds, callback){
        timer = seconds;
        if(timerInterval) clearInterval(timerInterval);
        timerInterval = setInterval(()=>{
            timer--;
            if(timer <= 0){ clearInterval(timerInterval); callback(); }
        }, 1000);
    }

    function draw(){
        background("#073870");
        let cx = width/2, cy = height/2;

        // Table
        push();
        stroke("#3e2003"); strokeWeight(8); fill("#b45f06");
        ellipse(cx,cy,tableW+40,tableH+40);
        fill("#1b5e20"); stroke("#144417"); strokeWeight(4);
        ellipse(cx,cy,tableW,tableH);
        pop();

        // Cards Placeholder
        fill(255, 30); noStroke();
        for(let i=0;i<5;i++) rect(cx-165+i*70,cy-40,55,80,5);

        // Positionnement joueurs
        let rx = tableW*0.52, ry = tableH*0.55;

        for(let i=0;i<nPlayers;i++){
            let angle = -Math.PI/2 + i*(2*Math.PI/nPlayers);
            let x = cx + rx*Math.cos(angle) - avatarW/2;
            let y = cy + ry*Math.sin(angle) - avatarH/2;

            if(imgPlayer) image(imgPlayer, x, y, avatarW, avatarH);

            if(playerData[i] && playerData[i].active){
                if(gameStarted && i === currentTurn){
                    push(); noFill();
                    let glow = 10 + sin(frameCount * 0.1) * 5;
                    strokeWeight(glow); stroke(255, 215, 0, 150);
                    rect(x - 5, y - 5, avatarW + 10, avatarH + 10, 15);
                    pop();
                }

                textAlign(CENTER);
                let textY = Math.sin(angle)>0?y+avatarH+15:y-35;

                let boxColor = playerData[i].isMe ? "rgba(0, 100, 200, 0.8)" : "rgba(0,0,0,0.8)";
                if(gameStarted && i === currentTurn) boxColor = "#FFD700";

                fill(boxColor);
                rect(x-10,textY,avatarW+20,45,8);

                fill(gameStarted && i === currentTurn ? 0 : 255);
                textSize(14);
                text(playerData[i].name, x+avatarW/2, textY+20);

                fill(gameStarted && i === currentTurn ? 0 : "#FFD700");
                text(playerData[i].chips + " J", x+avatarW/2, textY+38);
            }
        }

        fill(255); textSize(22); textAlign(CENTER);
        text("POT: 0 J",cx,cy+85);

        if(!gameStarted){
            let activeCount = playerData.filter(p=>p.active).length;
            if(activeCount === 2) { fill("#FFD700"); text("Début dans : "+timer+"s",cx,50); }
            else { fill(255, 150); text("Attente joueurs ("+activeCount+"/2)...", cx, 50); }
        } else {
            fill(255);
            text("Tour : " + playerData[currentTurn].name + " (" + timer + "s)", cx, 50);
        }
    }

    function windowResized(){
        resizeCanvas(windowWidth,windowHeight);
        initButtons();
    }
</script>
</body>
</html>
