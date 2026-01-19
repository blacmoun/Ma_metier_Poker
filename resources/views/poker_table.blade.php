<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Interface Poker Pro - Clean</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/p5.js/1.9.0/p5.min.js"></script>
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
    </style>
</head>
<body>
<script>
    let imgjoueur;
    let boutons = [];
    let playerData = [];
    const nPlayers = 9;
    const avatarW = 100;
    const avatarH = 125;
    const tableW = 900;
    const tableH = 400;

    function preload() {
        imgjoueur = loadImage("/img/joueur.png");
    }

    function calculatePositions() {
        let positions = [];
        let cx = width / 2;
        let cy = height / 2;
        let rx = tableW * 0.52;
        let ry = tableH * 0.55;

        for (let i = 0; i < nPlayers; i++) {
            let angle = -Math.PI/2 + i * (2 * Math.PI / nPlayers);
            let x = cx + rx * Math.cos(angle) - avatarW/2;
            let y = cy + ry * Math.sin(angle) - avatarH/2;
            positions.push({x, y, angle});
        }
        return positions;
    }

    function setup() {
        createCanvas(windowWidth, windowHeight);

        // Initialisation des données joueurs si vide
        for(let i=0; i<nPlayers; i++) {
            playerData[i] = { name: "", chips: 0, active: false };
        }

        initButtons();
    }

    function initButtons() {
        // Supprimer les anciens boutons si redimensionnement
        boutons.forEach(b => b.remove());
        boutons = [];

        let positions = calculatePositions();
        window.joueurs = positions;

        positions.forEach((j, i) => {
            let btn = createButton("Rejoindre");
            btn.size(100, 30);
            btn.position(j.x + avatarW/2 - 50, j.y + avatarH + 10);

            // Si la place est déjà prise, on cache le bouton immédiatement
            if (playerData[i].active) {
                btn.hide();
            }

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

            // CACHE LE BOUTON
            boutons[index].hide();

            // Envoi au serveur
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
        let cy = height / 2;

        // --- DESSIN TABLE ---
        push();
        stroke("#3e2003");
        strokeWeight(8);
        fill("#b45f06"); // Bordure bois
        ellipse(cx, cy, tableW + 40, tableH + 40);

        fill("#1b5e20"); // Tapis vert
        stroke("#144417");
        strokeWeight(4);
        ellipse(cx, cy, tableW, tableH);

        noFill(); // Ligne déco
        stroke(255, 20);
        ellipse(cx, cy, tableW - 60, tableH - 60);
        pop();

        // --- CARTES ---
        fill(255);
        noStroke();
        for(let i=0; i<5; i++){
            rect(cx - 165 + i*70, cy - 40, 55, 80, 5);
        }

        // --- JOUEURS ---
        window.joueurs.forEach((j, i) => {
            // Dessiner l'avatar
            image(imgjoueur, j.x, j.y, avatarW, avatarH);

            // Afficher infos seulement si la place est prise
            if (playerData[i].active) {
                textAlign(CENTER);
                let isBottom = Math.sin(j.angle) > 0;
                let textY = isBottom ? j.y + avatarH + 15 : j.y - 35;

                // Fond du texte
                fill(0, 180);
                rect(j.x - 10, textY, avatarW + 20, 45, 8);

                // Nom et Jetons
                fill(255);
                textSize(14);
                text(playerData[i].name, j.x + avatarW/2, textY + 20);
                fill("#FFD700");
                text(playerData[i].chips + " $", j.x + avatarW/2, textY + 38);
            }
        });

        // Pot
        fill(255);
        textSize(22);
        textAlign(CENTER);
        text("POT: 0 $", cx, cy + 85);
    }

    function windowResized() {
        resizeCanvas(windowWidth, windowHeight);
        initButtons();
    }
</script>
</body>
</html>
