# 🎵 Music EAN Scanner

**Versie:** 2.0.1  
**Auteur:** Daniel Stam  
**PrestaShop Compatibiliteit:** 1.7.0 - 8.x  
**Licentie:** MIT

---

## 📖 Inhoudsopgave

- [Overzicht](#-overzicht)
- [Features](#-features)
- [Installatie](#-installatie)
- [API Configuratie](#-api-configuratie)
- [Gebruik](#-gebruik)
- [Troubleshooting](#-troubleshooting)
- [Changelog](#-changelog)

---

## 🎯 Overzicht

Professionele PrestaShop module voor het snel en accuraat importeren van muziek producten via EAN/barcode scanning. Perfect voor muziekwinkels, platenzaken en online retailers.

### Ondersteunde API's

#### 🎵 **Discogs** (Aanbevolen - Gratis!)
- ✅ Grootste muziek database ter wereld (14+ miljoen releases)
- ✅ Gratis Personal Access Token
- ✅ Gedetailleerde metadata (artiest, jaar, label, genre, tracklist)
- ✅ Hoge kwaliteit cover afbeeldingen
- ⚠️ Geen prijzen (handmatig instellen)

**→ [Verkrijg gratis Discogs token](https://www.discogs.com/settings/developers)**

#### 🛒 **Bol.com** (Optioneel)
- ✅ Nederlandse producten
- ✅ Inclusief prijzen
- ⚠️ Vereist API credentials
- ⚠️ **Niet getest** - gebruik op eigen risico

**→ [Bol.com Partner Programma](https://partnerprogramma.bol.com/)**

---

## ✨ Features

### 🔍 **Intelligent Product Zoeken**
- Scan of voer EAN code in
- Automatische API call naar Discogs/Bol.com
- Product preview met alle details
- Handmatige controle voor import

### 📦 **Slim Voorraad Beheer**
- **Nieuw product:** Stel voorraad in en importeer direct
- **Bestaand product:** Verhoog voorraad met instelbaar aantal (+1, +5, +10, etc.)
- Automatische duplicate detectie
- Voorkomt dubbele producten
- Visuele feedback bij elke actie

### ⚡ **Auto-Submit Modus**
- Perfect voor USB barcode scanners
- Automatisch importeren na scan
- Groene/gele meldingen (verdwijnen na 3 sec)
- **Workflow:** Scan → Import → Klaar! (3 seconden per product)

### 🎯 **Automatische Categorie Detectie**
- Herkent CD's, Vinyl, DVD's, Blu-ray automatisch
- Categorieën worden bij installatie aangemaakt
- Handmatig aanpasbaar voor import

### 💰 **Prijs Beheer**
- Configureerbare prijs markup (percentage of vast bedrag)
- Handmatig aanpasbaar per product
- Originele prijs zichtbaar (bij Bol.com)

### 🔒 **Beveiliging**
- API keys opgeslagen als password velden
- Alleen admin toegang
- CSRF token beveiliging
- Logging zonder gevoelige data

---

## 📥 Installatie

### Stap 1: Download de Module

**Optie A: Via GitHub Releases (Aanbevolen)**
1. Ga naar [Releases](https://github.com/Daan026/prestashop-music-ean-scanner/releases)
2. Download de nieuwste `musiceanscanner.zip`

**Optie B: Handmatig ZIP maken**
1. Download of clone deze repository
2. Zorg dat de map `musiceanscanner` heet
3. Maak een ZIP van de hele map (inclusief alle submappen)

### Stap 2: Module Installeren

1. Ga naar **PrestaShop Admin → Modules → Module Manager**
2. Klik op **"Upload a module"**
3. Selecteer het `musiceanscanner.zip` bestand
4. Klik op **"Install"**

**✅ Bij installatie wordt automatisch aangemaakt:**
- Admin menu item: "Music Scanner" (onder Catalogus)
- Categorieën: CD's, Vinyl, DVD's, Blu-ray
- Configuratie met standaard waarden
- Logs map voor foutmeldingen

---

## 🔑 API Configuratie

### Optie 1: Discogs (Aanbevolen - Gratis!)

#### 1. Maak een Discogs Account
→ **[Registreer gratis op Discogs.com](https://www.discogs.com/users/create)**

#### 2. Genereer Personal Access Token
1. Ga naar **[Discogs Developer Settings](https://www.discogs.com/settings/developers)**
2. Scroll naar beneden naar **"Personal Access Tokens"**
3. Klik op **"Generate new token"**
4. Geef een naam op (bijv. "PrestaShop Import")
5. Kopieer de **token** (lange string met letters en cijfers)

⚠️ **Let op:** Gebruik de **Personal Access Token**, NIET de "Consumer Key/Secret"!

#### 3. Configureer in PrestaShop
1. Ga naar **Modules → Module Manager**
2. Zoek "Music EAN Scanner"
3. Klik op **"Configure"**
4. Vul in:
   - **API Bron:** Selecteer "Discogs"
   - **Discogs Personal Access Token:** Plak je token
   - **Prijs Markup:** Bijv. `20` voor +20% marge
   - **Standaard Voorraad:** Bijv. `1`
   - **Auto-Submit:** `Uit` (voor handmatige controle) of `Aan` (voor snelle bulk import)
5. Klik **"Instellingen Opslaan"**

✅ **Klaar! Je kunt nu producten importeren.**

---

### Optie 2: Bol.com (Optioneel)

⚠️ **Let op:** De Bol.com API integratie is **niet getest**. Gebruik op eigen risico. Discogs wordt aanbevolen.

#### 1. Word Bol.com Partner
→ **[Aanmelden als Bol.com Partner](https://partnerprogramma.bol.com/)**

#### 2. Vraag API Credentials aan
1. Log in op je Bol.com Partner account
2. Ga naar **API Instellingen**
3. Vraag **API Key** en **API Secret** aan
4. Wacht op goedkeuring (kan enkele dagen duren)

#### 3. Configureer in PrestaShop
1. Ga naar **Modules → Module Manager → Music EAN Scanner → Configure**
2. Vul in:
   - **API Bron:** Selecteer "Bol.com"
   - **Bol.com API Key:** Plak je API key
   - **Bol.com API Secret:** Plak je API secret
   - **Prijs Markup:** Bijv. `15` voor +15% marge
3. Klik **"Instellingen Opslaan"**

---

## 🎮 Gebruik

### Toegang tot de Scanner

1. Log in op PrestaShop Admin
2. Ga naar **Catalogus → Music Scanner**
3. Je ziet nu de scanner interface

---

### Scenario 1: Nieuw Product Importeren

#### Handmatige Modus (Auto-Submit UIT)

1. **Voer EAN in:** Type of scan de barcode (bijv. `0094638241720`)
2. **Klik "Zoeken"** of druk **Enter**
3. **Bekijk Preview:**
   - Titel, artiest, beschrijving
   - Cover afbeelding
   - Prijs (aanpasbaar)
   - Voorraad (aanpasbaar)
   - Categorie (automatisch gedetecteerd)
4. **Pas aan indien nodig:**
   - Wijzig prijs
   - Wijzig voorraad
   - Wijzig categorie
5. **Klik "Product Importeren"**
6. ✅ **Product is aangemaakt!**

#### Auto-Submit Modus (Auto-Submit AAN)

1. **Scan barcode** met USB scanner
2. ✅ **Product wordt automatisch geïmporteerd**
3. 🟢 **Groene melding:** "The Beatles - Abbey Road - Toegevoegd"
4. Melding verdwijnt na 3 seconden
5. **Scan volgende product**

**Workflow:** `Scan → Import → Klaar!` (3 seconden per product)

---

### Scenario 2: Voorraad Verhogen (Bestaand Product)

1. **Scan/voer EAN in** van bestaand product
2. 🟡 **Gele melding:** "Product bestaat al in voorraad!"
3. **Zie productinformatie:**
   - Huidige voorraad: **5**
   - Toevoegen: **+[1]** (aanpasbaar naar +5, +10, etc.)
4. **Wijzig aantal** indien nodig (bijv. +3)
5. **Knop verandert:** "Voorraad Verhogen (+3)"
6. **Klik op knop**
7. ✅ **Nieuwe voorraad:** 8

---

### Test EAN Codes

**The Beatles - Abbey Road:**
```
0094638241720
```

**Pink Floyd - The Dark Side of the Moon:**
```
5099902894027
```

⚠️ **Let op:** Gebruik deze codes alleen voor testen. Voor productie gebruik je eigen product EAN codes.

---

## 🔧 Troubleshooting

### "Product niet gevonden"

**Oorzaken:**
- EAN code bestaat niet in Discogs/Bol.com database
- EAN code is incorrect getypt
- Product is te nieuw (nog niet in database)

**Oplossingen:**
- ✅ Controleer de EAN code (13 cijfers)
- ✅ Probeer andere API bron (Discogs ↔ Bol.com)
- ✅ Zoek het product handmatig op Discogs.com om te verifiëren

---

### "Invalid consumer token" of "Authentication failed"

**Oorzaken:**
- API token is incorrect
- Token is verlopen
- Verkeerde token type gebruikt

**Oplossingen:**
- ✅ Genereer nieuwe token op [Discogs Developer Settings](https://www.discogs.com/settings/developers)
- ✅ Gebruik **Personal Access Token**, NIET Consumer Key/Secret
- ✅ Kopieer token zonder spaties voor/achter
- ✅ Sla opnieuw op in module instellingen

---

### Geen afbeelding zichtbaar

**Oorzaken:**
- Product heeft geen cover in database
- Afbeelding kon niet worden gedownload
- Schrijfrechten probleem

**Oplossingen:**
- ✅ Check of `/img/p/` map schrijfbaar is (chmod 755)
- ✅ Upload handmatig een afbeelding na import
- ✅ Check PHP error logs: `/var/logs/`

---

### Auto-Submit werkt niet

**Oorzaken:**
- Auto-Submit staat uit in instellingen
- Barcode scanner niet correct geconfigureerd

**Oplossingen:**
- ✅ Zet Auto-Submit AAN in module instellingen
- ✅ Test of scanner "Enter" stuurt na barcode
- ✅ Configureer scanner om Enter/Return te sturen

---

### Module verschijnt niet in menu

**Oorzaken:**
- Module niet correct geïnstalleerd
- Cache niet geleegd

**Oplossingen:**
- ✅ Herinstalleer de module
- ✅ Leeg PrestaShop cache: `rm -rf var/cache/*`
- ✅ Refresh browser (Ctrl+F5)

---

## 📋 Changelog

### v2.0.1 (2025-10-29)

**🔧 Verbeteringen:**
- ✅ PrestaShop 8.x compatibiliteit toegevoegd
- ✅ PHP 7.2+ requirement check bij installatie
- ✅ cURL extensie check bij installatie
- ✅ Betere error handling voor categorie aanmaak
- ✅ Verbeterde logging bij installatie
- ✅ Automatische permissie fix voor logs directory

**🐛 Bug Fixes:**
- ✅ Fix voor logs directory permissies op Windows/Linux
- ✅ Fix voor categorie aanmaak op verschillende PrestaShop versies
- ✅ Betere foutmeldingen bij installatie problemen

---

### v2.0.0 (2025-01-28)

**🎉 Nieuwe Features:**
- ✅ Discogs API integratie (gratis, 14+ miljoen releases)
- ✅ Slim voorraad beheer (verhoog voorraad bij duplicaten)
- ✅ Auto-Submit modus voor barcode scanners
- ✅ Automatische categorie detectie (CD's, Vinyl, DVD's, Blu-ray)
- ✅ Visuele feedback (groene/gele meldingen)
- ✅ Password velden voor API keys (beveiliging)
- ✅ Automatische categorie aanmaak bij installatie

**🔧 Verbeteringen:**
- ✅ Complete herschrijving van de codebase
- ✅ Professionele logging
- ✅ CSRF token beveiliging
- ✅ Duplicate detectie met voorraad update
- ✅ Configureerbare prijs markup

**🐛 Bug Fixes:**
- ✅ PHP memory limit problemen opgelost
- ✅ Cache problemen opgelost
- ✅ AJAX calls gerepareerd
- ✅ Image path correcties

---

### v1.0.0 (Initiële Release)

- ✅ Bol.com API integratie
- ✅ Basis EAN zoekfunctie
- ✅ Product import
- ✅ Afbeelding download

---

## 📄 Licentie

MIT License - Vrij te gebruiken voor commerciële en niet-commerciële doeleinden.

---

## 👨‍💻 Support

Voor vragen, bugs of feature requests:
- Check eerst de [Troubleshooting](#-troubleshooting) sectie
- Controleer PrestaShop error logs: `/var/logs/`
- Controleer module logs: `/var/logs/musicscanner.log`

---

**Gemaakt met ❤️ voor muziekliefhebbers en platenzaken**
