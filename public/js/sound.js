"use strict";

let backgroundStarted = false;
let elevatorStarted = false;

const musics = {
    elevator  : new Audio("/sound/Elevator.mp3"),
    background: new Audio("/sound/background-music.mp3"),
    jackpot   : new Audio("/sound/jackpot.mp3"),
    bad       : new Audio("/sound/bad.mp3"),
    shuffle   : new Audio("/sound/shuffle_card.mp3"),
    flip      : new Audio("/sound/flipcard.mp3"),
    coin      : new Audio("/sound/coin.mp3"),
    notif     : new Audio("/sound/notification.mp3")
};

// Configuration globale
for (let m in musics) {
    musics[m].volume = 0.5;
    musics[m].loop = false;
}
musics.background.loop = true;



// Elevator (au début)
function startElevatorMusic() {
    if (elevatorStarted) return;
    elevatorStarted = true;

    musics.elevator.currentTime = 0;
    musics.elevator.play().catch(() => {});
}

// Switch définitif vers background
function switchMusic() {
    if (backgroundStarted) return;
    backgroundStarted = true;

    musics.elevator.pause();
    musics.elevator.currentTime = 0;

    musics.background.currentTime = 0;
    musics.background.play().catch(() => {});
}


//  À appeler quand l’état du jeu change
function updateMusic(playerData, nPlayers) {
    const activeCount = playerData.filter(p => p && p.active).length;

    // Table complète → background
    if (activeCount === nPlayers) {
        switchMusic();
    }
}

function playFX(name) {
    const s = musics[name];
    if (!s) return;

    s.currentTime = 0;
    s.play().catch(() => {});
}

updateMusic(playerData, nPlayers);
