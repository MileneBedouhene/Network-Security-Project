# 🛡️ Projet SOC Open Source — Plateforme de Supervision et Réponse aux Incidents

## 📌 Présentation du projet

Ce projet consiste à concevoir et déployer un **SOC (Security Operations Center) Open Source** permettant la centralisation, la corrélation, l'analyse et la réponse aux incidents de sécurité au sein d'une infrastructure virtuelle.

L'objectif principal est de reproduire une architecture SOC réaliste basée entièrement sur des technologies open source afin de :

* Détecter les activités suspectes et les attaques.
* Centraliser les logs provenant de différentes machines et équipements.
* Superviser les événements de sécurité en temps réel.
* Automatiser la gestion et la remontée des alertes.
* Simuler des scénarios d'attaque afin de tester les mécanismes de détection.

---

## 🎯 Objectifs du projet

* Mettre en place une architecture SOC complète.
* Déployer une solution SIEM open source.
* Intégrer des outils de monitoring réseau et d'analyse de trafic.
* Centraliser les logs systèmes, réseau et sécurité.
* Détecter les comportements malveillants via des règles personnalisées.
* Automatiser la création d'incidents et d'alertes.
* Réaliser des tests d'attaque et de détection.
* Approfondir les compétences en cybersécurité défensive.

---

## 🏗️ Architecture du projet

L'architecture du SOC est composée de plusieurs machines virtuelles interconnectées représentant un environnement d'entreprise simplifié.

### Configuration réseau

Toutes les machines virtuelles sont dans le même réseau avec communication directe.

**Plage d'adressage :**

| Machine VM        | Adresse IP                                                                  |
| ----------------- | --------------------------------------------------------------------------- |
| **Ubuntu Server** | 192.168.100.10                                                              |
| **Windows 10**    | 192.168.100.20                                                              |
| **Windows AD**    | 192.168.100.30                                                              |
| **Core Plus**     | 192.168.100.40                                                              |
| **Kali Linux**    | 192.168.100.50                                                              |
| **APPLI**         | 192.168.100.60                                                              |
| **WAF**           | 192.168.100.70:8080 (WAF) / 192.168.100.70:80 (Application Vulnérable)     |
| **TheHive**       | 192.168.100.80                                                              |

---

## 🔹 Composants principaux

### 1. SIEM — Wazuh

Wazuh constitue le cœur du SOC.

Fonctionnalités principales :

* Collecte centralisée des logs.
* Détection d'intrusion.
* Analyse comportementale.
* Corrélation des événements.
* Gestion des agents.
* Monitoring des endpoints.
* Création de règles de détection personnalisées.

---

### 2. TheHive — Gestion des incidents

TheHive est utilisé comme plateforme de gestion des incidents.

Fonctionnalités :

* Création automatique de cas.
* Centralisation des alertes.
* Gestion du cycle de vie des incidents.
* Collaboration SOC.
* Investigation des événements.

---

### 3. Zeek — Analyse du trafic réseau

Zeek permet l'analyse approfondie du trafic réseau.

Fonctionnalités :

* Surveillance réseau.
* Détection d'activités anormales.
* Génération de logs réseau détaillés.
* Analyse DNS, HTTP, SSL et connexions.

---

### 4. FortiWeb — WAF (Web Application Firewall)

FortiWeb est utilisé dans le laboratoire pour simuler un environnement protégé par un Web Application Firewall.

Objectifs :

* Observer les attaques web.
* Analyser les logs WAF.
* Intégrer les événements dans le SIEM.
* Détecter les tentatives d'exploitation web.

---

## 🖥️ Infrastructure virtuelle

Le projet est déployé dans un environnement virtualisé.

**Technologies utilisées :**

* VMware / VirtualBox
* Kali Linux
* Ubuntu Server
* Windows Server
* Docker

---

## 🔍 Fonctionnement du SOC

### 1. Collecte des logs

Les agents Wazuh et les équipements réseau envoient leurs logs vers le serveur SIEM.

Sources surveillées :

* Logs système Linux
* Logs Windows
* Logs réseau
* Logs WAF
* Logs DNS
* Logs d'authentification

---

### 2. Analyse et corrélation

Wazuh analyse les événements reçus afin de :

* Détecter les anomalies.
* Identifier les comportements suspects.
* Corréler plusieurs événements.
* Générer des alertes de sécurité.

---

### 3. Réponse aux incidents

Les alertes critiques sont automatiquement envoyées vers TheHive afin de :

* Créer des incidents.
* Affecter des tâches d'investigation.
* Centraliser les preuves.
* Faciliter l'analyse SOC.

---

## ⚔️ Scénarios d'attaque simulés

Plusieurs scénarios de tests ont été réalisés afin d'évaluer l'efficacité du SOC, organisés selon la chaîne d'attaque MITRE ATT&CK.

---

### 🔎 1. Reconnaissance (100000 – 100099)

#### 🌍 Reconnaissance EXTERNE

| ID     | Règle               | OS | Description                                                                                          | Niveau |
| ------ | ------------------- | -- | ---------------------------------------------------------------------------------------------------- | ------ |
| 100001 | Ping Flood          | 🪟 | Détection d'un bombardement ICMP sur un seul hôte indiquant une phase active de découverte réseau.   | Low    |
| 100002 | Ping sweep          | 🪟 | Détection d'un balayage ICMP sur plusieurs hôtes indiquant une phase active de découverte réseau.    | Low    |
| 100004 | Scan ports agressif | 🪟 | Détection d'un scan rapide de ports révélant une tentative d'identification des services exposés.    | Low    |
| 100005 | Enum services       | 🪟 | Détection d'un scan de versions de services permettant d'identifier des vulnérabilités potentielles. | Low    |
| 100006 | Scan SMB            | 🪟 | Détection d'une énumération SMB visant à découvrir des partages et ressources Windows accessibles.   | Low    |

#### 🏠 Reconnaissance INTERNE

| ID     | Règle            | OS | Description                                                                                           | Niveau |
| ------ | ---------------- | -- | ----------------------------------------------------------------------------------------------------- | ------ |
| 100007 | Recon commandes  | 🪟 | Détection de commandes locales (whoami, ipconfig) indiquant une reconnaissance post-compromission.    | Low    |
| 100008 | Enum AD          | 🪟 | Identification d'une énumération Active Directory révélant une exploration des comptes et privilèges. | Low    |
| 100009 | Enum Linux users | 🐧 | Détection d'un accès au fichier /etc/passwd indiquant une tentative d'énumération des utilisateurs.  | Low    |

#### Brute Force

| ID     | Règle                   | OS | Description                                             | Niveau |
| ------ | ----------------------- | -- | ------------------------------------------------------- | ------ |
| 100011 | BruteForce Login        | 🪟 | Détection de Bruteforce au login +3 tentatives => alerte. | Low    |
| 100012 | BruteForce SSH password | 🐧 | Détection de bruteforce SSH password +3 tentatives => alerte. | Low    |
| 100013 | BruteForce user SSH     | 🐧 | Détection de bruteforce user SSH +3 tentatives => alerte. | Low    |

---

### ⚔️ 2. Delivery / Exploitation (100100 – 100199)

| ID            | Règle                               | OS | Description                                                                                                 | Niveau |
| ------------- | ----------------------------------- | -- | ----------------------------------------------------------------------------------------------------------- | ------ |
| 100101        | certutil abuse                      | 🪟 | Détection de l'utilisation de certutil pour télécharger un payload en contournant les protections.          | Medium |
| 100102        | mshta/regsvr32                      | 🪟 | Détection d'exécution de code distant via mshta ou regsvr32 utilisée dans les attaques fileless.            | Medium |
| 100103        | PowerShell obfusqué                 | 🪟 | Identification de commandes PowerShell obfusquées indiquant une tentative de dissimulation malveillante.    | High   |
| 100104        | Download fichier                    | 🐧 | Détection de téléchargement via wget/curl indiquant une récupération de payload sur Linux.                  | Medium |
| 100106/100107 | Command injection                   | 🐧 | Détection de commandes bash suspectes pouvant indiquer une exploitation par injection.                      | High   |
| 100108        | Execution /tmp                      | 🐧 | Détection de l'exécution de fichiers depuis /tmp, comportement courant des malwares Linux.                  | High   |
| 100109        | Attaque WEB bloquée par FortiWeb    | 🌐 | Détection de tentative d'exploitation web (SQL, XSS, LFI, Payload…)                                        | High   |

---

### 🧱 3. Persistence (100200 – 100299)

| ID     | Règle                | OS | Description                                                                                        | Niveau |
| ------ | -------------------- | -- | -------------------------------------------------------------------------------------------------- | ------ |
| 100201 | Scheduled task       | 🪟 | Détection de création de tâche planifiée utilisée pour maintenir un accès persistant.              | High   |
| 100202 | Registry Run         | 🪟 | Identification de modification des clés Run du registre assurant une exécution automatique.        | High   |
| 100203 | Cron modifié         | 🐧 | Détection d'une modification de crontab indiquant une persistance sur Linux.                       | Medium |
| 100204 | Ajout user Linux     | 🐧 | Identification de création d'un utilisateur pouvant servir de backdoor persistante.                | High   |
| 100205 | Modification sudoers | 🐧 | Détection de modification du fichier sudoers permettant une élévation de privilèges.               | High   |
| 100206 | SSH key              | 🐧 | Détection d'ajout de clé SSH permettant un accès persistant sans authentification.                 | Medium |

---

### 🎯 4. Command & Control (C2) (100300 – 100399)

| ID              | Règle                          | OS    | Description                                                                                              | Niveau   |
| --------------- | ------------------------------ | ----- | -------------------------------------------------------------------------------------------------------- | -------- |
| 100301/303/304  | Beacon / port rare / revshell  | 🪟    | Détection de connexions périodiques vers un serveur externe indiquant un C2 actif.                       | Medium   |
| 100302          | Multi-IP                       | 🪟    | Identification de connexions vers plusieurs IP révélant un comportement suspect ou malware.              | Medium   |
| 100303          | Port rare                      | 🪟    | Détection de communications sur des ports inhabituels souvent utilisés par des backdoors.                | Medium   |
| 100304          | Reverse shell                  | 🪟    | Détection d'une connexion sortante interactive vers un attaquant indiquant un reverse shell.             | Critical |
| 100305          | DNS exfiltration               | 🪟/🐧 | Détection de requêtes DNS anormales pouvant indiquer du tunneling ou C2 discret.                         | Critical |

---

### 📦 5. Exfiltration (100400 – 100499)

| ID     | Règle             | OS | Description                                                                                          | Niveau   |
| ------ | ----------------- | -- | ---------------------------------------------------------------------------------------------------- | -------- |
| 100401 | Compression       | 🪟 | Détection de création d'archives pouvant indiquer une préparation à l'exfiltration de données.       | High     |
| 100402 | Large File Upload | 🪟 | Transfert de données en masse.                                                                       | High     |
| 100403 | Netcat transfert  | 🪟 | Détection d'un transfert de données via netcat utilisé pour exfiltration rapide.                     | Critical |
| 100404 | USB copy          | 🐧 | Détection de copie vers un périphérique USB indiquant une exfiltration physique.                     | Medium   |

---

### 🏢 6. Active Directory Attacks (100500 – 100599)

| ID     | Règle           | OS | Description                                                                                   | Niveau   |
| ------ | --------------- | -- | --------------------------------------------------------------------------------------------- | -------- |
| 100501 | Kerberoasting   | 🏢 | Détection de requêtes TGS suspectes utilisées pour extraire des hashes de comptes de service. | Critical |
| 100502 | Pass-the-Hash   | 🏢 | Détection d'authentification NTLM basée sur un hash sans mot de passe en clair.              | High     |
| 100503 | Pass-the-Ticket | 🏢 | Détection de réutilisation de tickets Kerberos pour accès non autorisé.                      | High     |
| 100504 | Golden Ticket   | 🏢 | Détection de tickets Kerberos forgés permettant un accès total au domaine.                   | Critical |
| 100505 | DCSync          | 🏢 | Détection de tentative de réplication AD pour extraire les credentials.                      | Critical |

---

### 🚨 7. Defense Evasion (100600 – 100699)

| ID     | Règle        | OS | Description                                                                                                   | Niveau   |
| ------ | ------------ | -- | ------------------------------------------------------------------------------------------------------------- | -------- |
| 100601 | Firewall off | 🪟 | Détection de désactivation du pare-feu indiquant une tentative de contournement des protections.              | High     |
| 100602 | Log deletion | 🪟 | Détection de suppression de logs système visant à effacer les traces d'activité malveillante.                 | Critical |

---

## 📊 Résultats obtenus

Le projet a permis de :

* Détecter plusieurs types d'attaques.
* Centraliser efficacement les logs.
* Générer des alertes en temps réel.
* Automatiser la gestion des incidents.
* Mettre en place une architecture SOC fonctionnelle.
* Approfondir les connaissances en Blue Team.

---

## 🔐 Compétences développées

**Cybersécurité :**

* SOC Operations
* SIEM Engineering
* Threat Detection
* Incident Response
* Log Analysis
* Network Monitoring
* IDS/IPS
* Digital Forensics

**Techniques :**

* Linux Administration
* Virtualisation
* Docker
* Réseaux
* Scripts Python
* Configuration Wazuh
* Intégration TheHive
* Analyse Zeek

---

## 🚀 Perspectives d'amélioration

* Intégration de Cortex.
* Automatisation SOAR avancée.
* Déploiement Kubernetes.
* Intégration MITRE ATT&CK.
* Ajout de dashboards avancés.
* Détection basée sur Machine Learning.
* Intégration Elastic Stack.

---

## 📚 Conclusion

Ce projet de SOC Open Source représente une plateforme complète d'apprentissage et d'expérimentation en cybersécurité défensive.

Il permet de reproduire un environnement SOC réaliste tout en développant des compétences pratiques en supervision, détection d'intrusions, analyse de logs et réponse aux incidents.

Ce laboratoire constitue également une excellente base pour l'apprentissage des technologies Blue Team et des opérations SOC modernes.