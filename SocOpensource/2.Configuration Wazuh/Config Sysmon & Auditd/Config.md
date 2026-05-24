# Installation et Configuration — Sysmon (Windows) & Auditd (Linux)

Ce document couvre l'installation et la configuration de **Sysmon** sur Windows et **Auditd** sur Linux, deux outils complémentaires de surveillance système intégrés au pipeline Wazuh du lab SOC.

---

# Partie 1 — Sysmon (Windows 10 / Windows AD)

Sysmon (System Monitor) est un service Windows qui enregistre les activités système dans les journaux d'événements Windows. Il enrichit considérablement la télémétrie disponible pour Wazuh.

---

## 1. Vérifier si Sysmon est installé

### Via les services Windows

1. Ouvrir **Exécuter** (`Win + R`), taper `services.msc`
2. Rechercher `Sysmon64` ou `Sysmon`

- Présent → installé
- Absent → non installé

### Via l'invite de commande (en administrateur)

```cmd
sc query sysmon
```

- `RUNNING` → installé et actif
- `STOPPED` → installé mais arrêté
- `FAILED 1060` → non installé

### Via l'Observateur d'événements

Ouvrir `eventvwr.msc`, puis naviguer vers :

```
Applications and Services Logs > Microsoft > Windows > Sysmon > Operational
```

- Dossier présent → installé
- Dossier absent → non installé

---

## 2. Installer Sysmon

### Étape 1 — Télécharger Sysmon

Télécharger depuis le site officiel Microsoft Sysinternals :
- https://learn.microsoft.com/en-us/sysinternals/downloads/sysmon

Extraire l'archive `.zip`.

### Étape 2 — Préparer une configuration

Sysmon sans fichier de configuration génère peu d'événements utiles. Utiliser la configuration recommandée de SwiftOnSecurity :
- https://github.com/SwiftOnSecurity/sysmon-config

Télécharger `sysmonconfig-export.xml` et le placer dans le même dossier que l'exécutable Sysmon.

### Étape 3 — Installation

Ouvrir un **CMD en administrateur** dans le dossier Sysmon.

Installation avec configuration (recommandé) :

```cmd
sysmon64.exe -i sysmonconfig.xml
```

Installation simple (sans configuration) :

```cmd
sysmon64.exe -i
```

---

## 3. Vérifier le fonctionnement

```cmd
sc query sysmon
```

Dans l'Observateur d'événements, des événements doivent apparaître, notamment :

- **Event ID 1** — Création de processus
- **Event ID 3** — Connexion réseau
- **Event ID 1102** — Journal de sécurité effacé

---

## 4. Commandes utiles

Mettre à jour la configuration :

```cmd
sysmon64.exe -c sysmonconfig.xml
```

Désinstaller Sysmon :

```cmd
sysmon64.exe -u
```

---

## 5. Intégration avec Wazuh

Wazuh collecte automatiquement les événements Sysmon via le canal Windows Event Log. Aucune configuration supplémentaire n'est nécessaire si l'agent Wazuh est déjà installé — les Event IDs Sysmon remontent directement dans le Dashboard.

Exemples de détections couvertes :

| Event ID | Description                          |
| -------- | ------------------------------------ |
| 1        | Création de processus suspect        |
| 3        | Connexion réseau anormale            |
| 7        | Chargement de DLL                    |
| 11       | Création de fichier                  |
| 1102     | Suppression du journal de sécurité   |

---

# Partie 2 — Auditd (Linux — Ubuntu Server / Kali)

Auditd (Linux Audit Daemon) surveille les appels système et les accès aux fichiers au niveau du noyau. Il constitue l'équivalent Linux de Sysmon pour la télémétrie des endpoints.

---

## 1. Vérifier si Auditd est installé

### Via le gestionnaire de paquets

```bash
# Debian / Ubuntu
dpkg -l | grep auditd

# CentOS / RHEL / Fedora
rpm -qa | grep audit
```

### Via le service systemd

```bash
systemctl status auditd
```

- `active (running)` → installé et actif
- `inactive (dead)` → installé mais arrêté
- `Unit auditd.service could not be found` → non installé

### Via la commande directe

```bash
which auditd
# ou
auditd --version
```

---

## 2. Installer Auditd

```bash
# Debian / Ubuntu
sudo apt update
sudo apt install auditd audispd-plugins -y

# CentOS / RHEL 7
sudo yum install audit audit-libs -y

# CentOS / RHEL 8+ / Fedora
sudo dnf install audit audit-libs -y
```

---

## 3. Démarrer et activer Auditd

```bash
sudo systemctl start auditd
sudo systemctl enable auditd
sudo systemctl status auditd
```

Résultat attendu :

```
● auditd.service - Security Auditing Service
     Loaded: loaded (/lib/systemd/system/auditd.service; enabled)
     Active: active (running)
```

---

## 4. Fichiers importants

| Fichier                         | Rôle                                    |
| ------------------------------- | --------------------------------------- |
| `/etc/audit/auditd.conf`        | Configuration principale du daemon      |
| `/etc/audit/audit.rules`        | Règles d'audit actives                  |
| `/etc/audit/rules.d/*.rules`    | Règles modulaires (approche recommandée)|
| `/var/log/audit/audit.log`      | Fichier de logs principal               |

---

## 5. Configurer Auditd

### Fichier de configuration principal

```bash
sudo nano /etc/audit/auditd.conf
```

```ini
# Taille maximale du fichier log (en Mo)
max_log_file = 50

# Action quand le fichier est plein
max_log_file_action = ROTATE

# Nombre de fichiers de rotation à conserver
num_logs = 5

# Action si le disque est plein
disk_full_action = SUSPEND

# Espace disque minimum avant alerte (Mo)
space_left = 75
space_left_action = SYSLOG
```

---

## 6. Règles d'audit personnalisées

Créer un fichier de règles dédié :

```bash
sudo nano /etc/audit/rules.d/custom.rules
```

```bash
# Connexions et déconnexions
-w /var/log/wtmp -p wa -k logins
-w /var/log/btmp -p wa -k logins
-w /var/log/lastlog -p rw -k logins

# Fichiers système sensibles
-w /etc/passwd -p wa -k user-modify
-w /etc/shadow -p wa -k user-modify
-w /etc/group -p wa -k group-modify
-w /etc/sudoers -p wa -k sudoers-modify

# Usage de sudo
-w /usr/bin/sudo -p x -k sudo_usage

# Configuration réseau
-w /etc/hosts -p wa -k network-modify
-w /etc/network/ -p wa -k network-modify

# Exécution de binaires (appels système)
-a always,exit -F arch=b64 -S execve -k exec_commands
-a always,exit -F arch=b32 -S execve -k exec_commands

# Clés SSH
-w /home/ -p wa -k ssh_keys
-w /root/.ssh -p wa -k ssh_keys
```

### Appliquer les règles

```bash
sudo augenrules --load
# ou
sudo service auditd restart
```

### Vérifier les règles actives

```bash
sudo auditctl -l
```

---

## 7. Consulter les logs

### Lecture directe

```bash
sudo cat /var/log/audit/audit.log
```

### Recherche avec `ausearch`

```bash
# Par clé de règle
sudo ausearch -k user-modify

# Par utilisateur
sudo ausearch -ua root

# Par période
sudo ausearch --start today
sudo ausearch --start yesterday --end now

# Par type d'événement
sudo ausearch -m USER_LOGIN
sudo ausearch -m EXECVE
```

### Rapports avec `aureport`

```bash
sudo aureport            # Rapport global
sudo aureport -au        # Authentifications
sudo aureport -x         # Exécutions
sudo aureport --failed   # Échecs
```

---

## 8. Commandes utiles

```bash
sudo systemctl start auditd       # Démarrer
sudo systemctl stop auditd        # Arrêter
sudo systemctl restart auditd     # Redémarrer
sudo auditctl -D                  # Vider les règles temporairement
sudo auditctl -e 2                # Verrouiller les règles jusqu'au reboot
```

---

## 9. Intégration avec Wazuh

Ajouter dans `/var/ossec/etc/ossec.conf` sur la machine Linux concernée :

```xml
<localfile>
  <log_format>audit</log_format>
  <location>/var/log/audit/audit.log</location>
</localfile>
```

Redémarrer l'agent :

```bash
sudo systemctl restart wazuh-agent
```

Événements remontés dans Wazuh via Auditd :

| Type d'événement        | Event Auditd    | Description                          |
| ----------------------- | --------------- | ------------------------------------ |
| Connexion utilisateur   | `USER_LOGIN`    | Authentification réussie ou échouée  |
| Exécution de commande   | `EXECVE`        | Toute exécution de binaire           |
| Modification de fichier | `SYSCALL write` | Écriture dans un fichier sensible    |
| Escalade de privilèges  | `USER_CMD`      | Usage de sudo                        |
| Suppression de fichier  | `SYSCALL unlink`| Suppression de fichiers              |

---

# Récapitulatif — Couverture par outil

| Capacité                        | Sysmon (Windows) | Auditd (Linux) |
| ------------------------------- | :--------------: | :------------: |
| Création de processus           | Event ID 1       | `EXECVE`       |
| Connexions réseau               | Event ID 3       | —              |
| Modification de fichiers sensibles | Event ID 11   | `SYSCALL write`|
| Usage de sudo / élévation       | —                | `USER_CMD`     |
| Suppression de logs             | Event ID 1102    | `SYSCALL unlink`|
| Authentification                | —                | `USER_LOGIN`   |
| Ajout de clés SSH               | —                | Règle `-w /root/.ssh` |