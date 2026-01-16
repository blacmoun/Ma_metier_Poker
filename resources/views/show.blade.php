<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Interface Poker</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/p5.js/1.9.0/p5.min.js"></script>
</head>
<body>
<script>
    let canvas;//le canvas si besoin pour dessiner (image)

    let imgjoueur

    function preload() {
        imgjoueur = loadImage("/img/joueur.png");
    }

    function setup() {
        createCanvas(windowWidth, windowHeight);

        canvas=document.getElementById("defaultCanvas0");
        ctx=canvas.getContext("2d");

    }

    function draw() {
        background("#073870");

        noStroke();

        // Ovale externe
        fill("#FF8C00");
        ellipse(width / 2, height / 2,900,350);

        // Ovale interne
        fill("#228B22");
        ellipse(width / 2, height / 2,800,300);

        // Cartes
        fill("#FFFFFF");
        let cardWidth = 60;
        let cardHeight = 90;
        let spacing = 70;

        for (let i = 0; i < 5; i++) {
            let x = width / 2 - (spacing * 2) + i * spacing -30;
            let y = height / 2 - cardHeight / 2 -30;
            rect(x, y, cardWidth, cardHeight, 8);
        }

        // Joueurs
        fill("#FFFFFF");
        stroke("#000000");
        strokeWeight(2);

        // Positions autour de la table
        let cx = width / 2;
        let cy = height / 2;

        let avatarW = 120;  // largeur
        let avatarH = 150;  // hauteur

        image(imgjoueur, cx - 400, cy - 250, avatarW, avatarH); // J1
        image(imgjoueur, cx -100,  cy - 310, avatarW, avatarH); // J2
        image(imgjoueur, cx + 230, cy - 275, avatarW, avatarH); // J3

        image(imgjoueur, cx + 470 ,cy -75, avatarW, avatarH);       // J4
        image(imgjoueur, cx + 350, cy + 75, avatarW, avatarH); // J5

        image(imgjoueur, cx + 125, cy + 150, avatarW, avatarH); // J6
        image(imgjoueur, cx - 150, cy + 160, avatarW, avatarH); // J7
        image(imgjoueur, cx - 450, cy + 100, avatarW, avatarH); // J8

        image(imgjoueur, cx - 575, cy - 80, avatarW, avatarH);       // J9

        // Texte sous les cartes
        let value_mise = 0;
        fill("#000000");
        textSize(30);
        textAlign(CENTER);
        text("* : " + value_mise, width / 2, height / 2 + 60);
        }

    function windowResized() {
            resizeCanvas(windowWidth, windowHeight);
        }


</script>


