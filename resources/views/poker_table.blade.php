<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Interface Poker Pro - Clean</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/p5.js/1.9.0/p5.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { margin: 0; padding: 0; overflow: hidden; background-color: #073870; }
        button {
            font-family: 'Segoe UI', sans-serif;
            transition: all 0.2s;
            background: rgba(17, 17, 17, 0.8);
            color: #FFD700;
            border: 1px solid #FFD700;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
        }
        button:hover { background: #FFD700; color: #000; }

        /* ZONE P5 = 60% HAUT */
        #p5-zone {
            position: fixed;
            top: 0;
            left: 0;
            width: 60%;
            height: 60vh;
            z-index: 0;
            pointer-events: none;
        }
    </style>
</head>
<body>

<!-- ZONE DU CANVAS P5 (60% HAUT) -->
<div id="p5-zone"></div>

<script>
    let imgjoueur;
    let boutons = [];
    let playerData = [];
    let difx = 150;
    const nPlayers = 9;
    const avatarW = 75;
    const avatarH = 100;
    const tableW = 525;
    const tableH = 250;

    function preload() {
        imgjoueur = loadImage("/img/joueur.png");
    }

    function calculatePositions() {
        let positions = [];
        let cx = width / 2 ;
        let cy = height / 2 -difx;
        let rx = tableW * 0.52;
        let ry = tableH * 0.52;


        for (let i = 0; i < nPlayers; i++) {
            let angle = -Math.PI/2 + i * (2 * Math.PI / nPlayers);
            let x = cx + rx * Math.cos(angle) - avatarW/2;
            let y = cy + ry * Math.sin(angle) - avatarH/2;
            positions.push({x, y, angle});
        }
        return positions;
    }

    function setup() {
        let canvas = createCanvas(windowWidth, windowHeight);
        canvas.parent("p5-zone");
        canvas.style('position', 'absolute');
        canvas.style('top', '0');
        canvas.style('left', '0');
        canvas.style('z-index', '0');

        for(let i=0; i<nPlayers; i++) {
            playerData[i] = { name: "", chips: 0, active: false };
        }

        initButtons();
    }

    function initButtons() {
        boutons.forEach(b => b.remove());
        boutons = [];

        let positions = calculatePositions();
        window.joueurs = positions;

        positions.forEach((j, i) => {
            let btn = createButton("Rejoindre");
            btn.size(100, 30);
            btn.position(j.x + avatarW/2 - 50, j.y + avatarH - 100);
            btn.style('z-index', '2');

            if (playerData[i].active) btn.hide();

            btn.mousePressed(() => inscrireJoueur(i));
            boutons.push(btn);
        });
    }

    async function inscrireJoueur(index) {
        let nom = prompt("Votre nom :");
        if(nom && nom.trim() !== ""){
            playerData[index].name = nom;
            playerData[index].chips = 1000;
            playerData[index].active = true;
            boutons[index].hide();

            try {
                await fetch("{{ url('/players') }}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ name: nom, chips: 1000 })
                });
            } catch(err) { console.error(err); }
        }
    }

    function draw() {
        background("#073870");
        let cx = width / 2;
        let cy = height / 2 -difx;

        push();
        stroke("#3e2003");
        strokeWeight(8);
        fill("#b45f06");
        ellipse(cx, cy, tableW + 40, tableH + 40);

        fill("#1b5e20");
        stroke("#144417");
        strokeWeight(4);
        ellipse(cx, cy, tableW, tableH);

        noFill();
        stroke(255, 20);
        ellipse(cx, cy, tableW - 60, tableH - 60);
        pop();

        fill(255);
        noStroke();
        for(let i=0; i<5; i++){
            rect(cx - 165 + i*70, cy - 40, 55, 80, 5);
        }

        window.joueurs.forEach((j, i) => {
            image(imgjoueur, j.x, j.y, avatarW, avatarH);

            if (playerData[i].active) {
                textAlign(CENTER);
                let isBottom = Math.sin(j.angle) > 0;
                let textY = isBottom ? j.y + avatarH + 15 : j.y - 35;

                fill(0, 180);
                rect(j.x - 10, textY, avatarW + 20, 45, 8);

                fill(255);
                textSize(14);
                text(playerData[i].name, j.x + avatarW/2, textY + 20);
                fill("#FFD700");
                text(playerData[i].chips + " *", j.x + avatarW/2, textY + 38);
            }
        });

        fill(255);
        textSize(22);
        textAlign(CENTER);
        text("POT: 0 *", cx, cy + 85);
    }

    function windowResized() {
        resizeCanvas(windowWidth, windowHeight);
        initButtons();
    }
</script>

<!-- MENU BOOTSTRAP = 40% BAS -->
<div class="container-fluid navbar-dark"
     style="position: fixed; bottom: 0; left: 0; width: 100%;
            height: 40vh;
            background: rgba(0,0,0,0.55);
            z-index: 1; overflow-y: auto;">

    <!-- Menu du bas -->
    <ul class="nav nav-tabs mb-3" id="menuTabs">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#cards">Cartes</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#info">Info</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#stats">Stats</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#history">Historique</button></li>
    </ul>

    <div class="row">
        <div class="col-md-6">
            <div class="tab-content border bg-white p-3" style="height:200px;">
                <div class="tab-pane fade show active" id="cards"><textarea class="form-control" placeholder="Zone Cartes"></textarea></div>
                <div class="tab-pane fade" id="info"><textarea class="form-control" placeholder="Zone info"></textarea></div>
                <div class="tab-pane fade" id="stats"><textarea class="form-control" placeholder="Zone Stats"></textarea></div>
                <div class="tab-pane fade" id="history"><textarea class="form-control" placeholder="Zone Historique"></textarea></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="d-flex justify-content-start gap-2 mb-3">
                <button class="btn btn-dark">Jouer</button>
                <button class="btn btn-dark">Quitter</button>
                <button class="btn btn-dark">Miser</button>
                <button class="btn btn-danger">Se coucher</button>
            </div>

            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="fw-bold">* : 1000</span>
                <input type="text" class="form-control w-50" placeholder="*=">
            </div>

            <div class="row g-2">
                <div class="col-6"><button class="btn btn-dark w-100">10</button></div>
                <div class="col-6"><button class="btn btn-dark w-100">100</button></div>
                <div class="col-6"><button class="btn btn-dark w-100">1</button></div>
                <div class="col-6"><button class="btn btn-dark w-100">All In</button></div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
