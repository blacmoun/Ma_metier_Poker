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
        fill("#b45f06");
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

        image(imgjoueur, cx - 400, cy - 250, avatarW, avatarH);// J1
        text("user",cx -350, cy -240 -50)
        text("*:",cx -350, cy -200 -50)// point J1

        image(imgjoueur, cx -100,  cy - 310, avatarW, avatarH);// J2
        text("user", cx -40, cy -300 -40)
        text("*:", cx -40, cy -260 -40)// point J2

        image(imgjoueur, cx + 230, cy - 275, avatarW, avatarH); // J3
        text("user", cx + 290, cy - 275 -30)
        text("*:", cx +290, cy- 235 -30)// point J3

        image(imgjoueur, cx + 470 ,cy -75, avatarW, avatarH);// J4
        text("user", cx + 530, cy - 70 -30)
        text("*:", cx + 530, cy - 30 -30)// point J4

        image(imgjoueur, cx + 350, cy + 75, avatarW, avatarH); // J5
        text("user", cx + 410, cy +300 -60)
        text("*:", cx + 410, cy +340 -60) // point J5

        image(imgjoueur, cx - 150, cy + 160, avatarW, avatarH); // J6
        text("user", cx +190, cy +380 -60)
        text("*:", cx +190, cy +420 -60)// point J6

        image(imgjoueur, cx + 125, cy + 150, avatarW, avatarH); // J7
        text("user", cx -90, cy +350 -20)
        text("*:", cx -90, cy +390 -20) // point J7

        image(imgjoueur, cx - 575, cy - 80, avatarW, avatarH);// J8
        text("user", cx -365, cy +260, -60)
        text("*:", cx -365, cy +300, -60)

        image(imgjoueur, cx - 450, cy + 100, avatarW, avatarH); // J9
        text("user", cx -480, cy -130, -60)
        text("*:", cx -480, cy -90, -60)//point J9

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


