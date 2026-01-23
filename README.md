# ma-poker

## Description
**ma-poker** est une application de poker développée avec **Laravel**. Elle permet de jouer et gérer des parties de poker en ligne.

---

## Table des matières
- [Description](#description)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Lancement de l'application](#lancement-de-lapplication)
- [Licence](#licence)
- [Contact](#contact)

---

## Prérequis
Avant d’installer le projet, assurez-vous d’avoir :

- **PHP** >= 8.4.1
- **MySQL** >= 8.0 ou **MariaDB** >= 10.5
- **Composer** >= 2.8.11
- **Node.js** et **npm**
- IDE (PhpStorm, Visual Studio Code, etc.)
- OS supportés : Windows 11, MacOS 26.0.1
- **Laravel** >= 12.46.0

---

## Installation

### 1. Cloner le dépôt
```bash
git clone <repository-url>
```

### 2. Installer les dépendances PHP et JS
```bash
composer install
npm install
npm run dev
```

### 3. Configurer l’environnement
Copiez le fichier `.env.example` et renommez-le `.env` :
```bash
cp .env.example .env
```
Puis configurez votre base de données et autres variables nécessaires.

## Lancement de l'application

### Avec le serveur intégré de Laravel
```bash
php artisan serve
```
L’application sera accessible sur [http://127.0.0.1:8000](http://127.0.0.1:8000) ou via [https://ma-poker.cld.education/](https://ma-poker.cld.education/).

---

## Licence
Ce projet est sous licence [MIT](LICENSE).

---

## Contact
Pour toute question ou collaboration :  
- amin.deabreu@eduvaud.ch  
- gatien.clerc@eduvaud.ch
- damien.garcia@eduvaud.ch
- sacha.volery@eduvaud.ch

