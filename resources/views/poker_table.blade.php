<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Poker 2-Player Table</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/p5.js/1.9.0/p5.min.js"></script>
    <style>
        body { margin:0; padding:0; overflow:hidden; background:#073870; }
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
    </style>
</head>
<body>
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
    let timer = 0;
    const startCountdownTime = 5;
    const turnDuration = 5;
    let timerInterval;
    let pollInterval;
    let currentTurn = 0;

    function preload(){
        imgPlayer = loadImage("/img/joueur.png");
    }

    function calculatePositions(){
        let positions = [];
        let cx = width/2;
        let cy = height/2;
        let rx = tableW*0.52;
        let ry = tableH*0.55;
        for(let i=0;i<nPlayers;i++){
            let angle = -Math.PI/2 + i*(2*Math.PI/nPlayers);
            let x = cx + rx*Math.cos(angle) - avatarW/2;
            let y = cy + ry*Math.sin(angle) - avatarH/2;
            positions.push({x,y,angle});
        }
        return positions;
    }

    function setup(){
        createCanvas(windowWidth, windowHeight);
        resetGameState();
        createLogoutButton();

        // CHARGEMENT INITIAL : On récupère les joueurs déjà présents
        loadPlayers();

        // POLLING : On vérifie toutes les 2s si la table a changé
        pollInterval = setInterval(loadPlayers, 2000);
    }

    function resetGameState() {
        gameStarted = false;
        timer = 0;
        currentTurn = 0;
        if(timerInterval) clearInterval(timerInterval);
        for(let i=0;i<nPlayers;i++) playerData[i]={name:"",chips:0,active:false};
        initButtons();
    }

    function initButtons(){
        buttons.forEach(b=>b.remove());
        buttons=[];
        let pos = calculatePositions();
        window.playersPos = pos;
        pos.forEach((p,i)=>{
            let btn = createButton("Join");
            btn.size(100,30);
            btn.position(p.x+avatarW/2-50,p.y+avatarH+10);
            // On cache le bouton si un joueur est déjà là
            if(playerData[i] && playerData[i].active) btn.hide();
            btn.mousePressed(()=>joinPlayer(i));
            buttons.push(btn);
        });
    }

    async function loadPlayers(){
        try{
            const res = await fetch("/game");
            const game = await res.json();

            let countBefore = playerData.filter(p => p.active).length;

            game.players.forEach((p, i) => {
                if(i < nPlayers){
                    playerData[i] = { name: p.name, chips: p.chips, active: true };
                    if(buttons[i]) buttons[i].hide();
                }
            });

            // Si on vient de passer à 2 joueurs, on lance le compte à rebours
            let countAfter = playerData.filter(p => p.active).length;
            if(!gameStarted && countAfter === 2 && countBefore < 2){
                startCountdown(startCountdownTime, () => {
                    gameStarted = true;
                    startTurnTimer();
                });
            }
        }catch(e){ console.error("Update error:", e); }
    }

    async function joinPlayer(index){
        let name = prompt("Enter your name:");
        if(!name||name.trim()==="") return;
        try{
            const res = await fetch("/join",{
                method:"POST",
                headers:{
                    "Content-Type":"application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({name})
            });
            const data = await res.json();
            if(!res.ok){ alert(data.error); return; }

            // Rechargement immédiat après avoir rejoint
            await loadPlayers();
        }catch(e){console.error(e); alert("Server error");}
    }

    function createLogoutButton(){
        if(logoutBtn) logoutBtn.remove();
        logoutBtn = createButton("Log Out");
        logoutBtn.position(20, 20);
        logoutBtn.size(100,30);
        logoutBtn.mousePressed(async ()=>{
            try{
                await fetch("/logout",{
                    method:"POST",
                    headers:{"X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content}
                });
                resetGameState();
            }catch(e){ console.error(e); }
        });
    }

    function startTurnTimer(){
        currentTurn = 0;
        startCountdown(turnDuration, nextTurn);
    }

    function nextTurn(){
        currentTurn = (currentTurn + 1) % nPlayers;
        startCountdown(turnDuration, nextTurn);
    }

    function startCountdown(seconds, callback){
        timer = seconds;
        if(timerInterval) clearInterval(timerInterval);
        timerInterval = setInterval(()=>{
            timer--;
            if(timer <= 0){
                clearInterval(timerInterval);
                callback();
            }
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

        // Cards
        fill(255, 50); noStroke();
        for(let i=0;i<5;i++) rect(cx-165+i*70,cy-40,55,80,5);

        // Players
        if(window.playersPos){
            window.playersPos.forEach((p,i)=>{
                // HIGHLIGHT TOUR ACTIF
                if(gameStarted && i === currentTurn){
                    push();
                    noFill();
                    let glow = 10 + sin(frameCount * 0.1) * 5;
                    strokeWeight(glow);
                    stroke(255, 215, 0, 150);
                    rect(p.x - 5, p.y - 5, avatarW + 10, avatarH + 10, 15);
                    pop();
                }

                if(imgPlayer) image(imgPlayer,p.x,p.y,avatarW,avatarH);

                if(playerData[i] && playerData[i].active){
                    textAlign(CENTER);
                    let textY = Math.sin(p.angle)>0?p.y+avatarH+15:p.y-35;
                    fill(gameStarted && i === currentTurn ? "#FFD700" : "rgba(0,0,0,0.8)");
                    rect(p.x-10,textY,avatarW+20,45,8);
                    fill(gameStarted && i === currentTurn ? 0 : 255);
                    textSize(14);
                    text(playerData[i].name, p.x+avatarW/2, textY+20);
                    fill(gameStarted && i === currentTurn ? 0 : "#FFD700");
                    text(playerData[i].chips+" $", p.x+avatarW/2, textY+38);
                }
            });
        }

        // UI
        fill(255); textSize(22); textAlign(CENTER);
        text("POT: 0 $",cx,cy+85);

        if(!gameStarted && playerData.filter(p=>p.active).length===2){
            fill("#FFD700");
            text("Game starts in: "+timer+"s",cx,50);
        } else if(gameStarted){
            fill(255);
            let activeName = playerData[currentTurn].name;
            text("Turn: " + activeName + " (" + timer + "s)", cx, 50);
        }
    }

    function windowResized(){
        resizeCanvas(windowWidth,windowHeight);
        initButtons();
    }
</script>
</body>
</html>
