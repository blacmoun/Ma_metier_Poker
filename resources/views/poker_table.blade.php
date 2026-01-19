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
    const nPlayers = 2; // max 2 players
    const avatarW = 100;
    const avatarH = 125;
    const tableW = 900;
    const tableH = 400;

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
        createCanvas(windowWidth,windowHeight);
        for(let i=0;i<nPlayers;i++) playerData[i]={name:"",chips:0,active:false};
        initButtons();
        loadPlayers();
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
            if(playerData[i].active) btn.hide();
            btn.mousePressed(()=>joinPlayer(i));
            buttons.push(btn);
        });
    }

    async function loadPlayers(){
        try{
            const res = await fetch("/game");
            const game = await res.json();
            game.players.forEach((p,i)=>{
                if(i<nPlayers){
                    playerData[i]={name:p.name,chips:p.chips,active:true};
                    if(buttons[i]) buttons[i].hide();
                    // If this player matches our session, show logout button
                    @if(session('player_token'))
                    if(p.session_token === "{{ session('player_token') }}"){
                        createLogoutButton(i);
                    }
                    @endif
                }
            });
        }catch(e){console.error(e);}
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

            playerData[index]={name:data.player.name,chips:data.player.chips,active:true};
            if(buttons[index]) buttons[index].hide();

            // Show logout button for **current player**
            createLogoutButton(index);

        }catch(e){console.error(e); alert("Server error");}
    }

    // Only current player logout
    function createLogoutButton(index){
        // Remove previous button if exists
        if(logoutBtn) logoutBtn.remove();

        // Create button **on the left side** of the screen
        logoutBtn = createButton("Log Out");
        logoutBtn.position(20, 50); // fixed left position
        logoutBtn.size(100, 30);
        logoutBtn.mousePressed(async ()=>{
            try{
                const res = await fetch("/logout", {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                const data = await res.json();
                console.log(data.message);

                // Reset current player locally
                playerData[index] = { name:"", chips:0, active:false };
                initButtons(); // show join buttons
                logoutBtn.remove(); // remove button from screen
            }catch(e){
                console.error(e);
                alert("Logout failed");
            }
        });
    }

    function draw(){
        background("#073870");
        let cx=width/2, cy=height/2;

        // Table
        push();
        stroke("#3e2003"); strokeWeight(8); fill("#b45f06");
        ellipse(cx,cy,tableW+40,tableH+40);
        fill("#1b5e20"); stroke("#144417"); strokeWeight(4);
        ellipse(cx,cy,tableW,tableH);
        noFill(); stroke(255,20); ellipse(cx,cy,tableW-60,tableH-60);
        pop();

        // Cards
        fill(255); noStroke();
        for(let i=0;i<5;i++) rect(cx-165+i*70,cy-40,55,80,5);

        // Players
        window.playersPos.forEach((p,i)=>{
            image(imgPlayer,p.x,p.y,avatarW,avatarH);
            if(playerData[i].active){
                textAlign(CENTER);
                let textY = Math.sin(p.angle)>0?p.y+avatarH+15:p.y-35;
                fill(0,180); rect(p.x-10,textY,avatarW+20,45,8);
                fill(255); textSize(14);
                text(playerData[i].name,p.x+avatarW/2,textY+20);
                fill("#FFD700"); text(playerData[i].chips+" $",p.x+avatarW/2,textY+38);
            }
        });

        // Pot
        fill(255); textSize(22); textAlign(CENTER);
        text("POT: 0 $",cx,cy+85);
    }

    function windowResized(){
        resizeCanvas(windowWidth,windowHeight);
        initButtons();
    }
</script>
</body>
</html>
