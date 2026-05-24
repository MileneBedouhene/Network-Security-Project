# Installation et Configuration de TheHive — Intégration Wazuh

---

## Architecture cible

```
Wazuh Agent
      ↓
Wazuh Manager
      ↓
OpenSearch (logs)
      ↓
TheHive API (cases)
      ↓
Cassandra (storage)
```

---

## 1. Configuration initiale du serveur TheHive

### Adresse IP statique

```bash
sudo ifconfig ens37 192.168.100.80 netmask 255.255.255.0
```

### Mise à jour du système

```bash
sudo apt update && sudo apt upgrade -y
```

### Installation des dépendances

```bash
sudo apt install wget curl gnupg apt-transport-https software-properties-common -y
```

---

## 2. Installation de Java 17

```bash
sudo apt install openjdk-17-jdk -y
java -version
```

---

## 3. Installation et configuration de OpenSearch

### Ajouter le dépôt

```bash
wget -qO - https://artifacts.opensearch.org/publickeys/opensearch.pgp | sudo apt-key add -
echo "deb https://artifacts.opensearch.org/releases/bundle/opensearch/2.x/apt stable main" | sudo tee /etc/apt/sources.list.d/opensearch-2.x.list
sudo apt update
sudo apt install opensearch -y
```

### Configurer OpenSearch

```bash
sudo nano /etc/opensearch/opensearch.yml
```

```yaml
cluster.name: thehive-cluster
node.name: node-1

network.host: 0.0.0.0
http.port: 9200

discovery.type: single-node

plugins.security.disabled: true
```

### Configurer la mémoire JVM

```bash
sudo nano /etc/opensearch/jvm.options
```

```
-Xms1g
-Xmx1g
```

### Démarrer OpenSearch

```bash
sudo systemctl daemon-reload
sudo systemctl enable opensearch
sudo systemctl restart opensearch
```

### Vérifier le fonctionnement

```bash
curl http://localhost:9200
```

Résultat attendu :

```json
{
  "name" : "node-1",
  "cluster_name" : "thehive-cluster",
  "version" : {
    "distribution" : "opensearch",
    "number" : "2.19.5"
  },
  "tagline" : "The OpenSearch Project: https://opensearch.org/"
}
```

---

## 4. Installation et configuration de Cassandra

### Ajouter le dépôt

```bash
curl -sL https://dlcdn.apache.org/cassandra/KEYS | sudo gpg --dearmor -o /etc/apt/trusted.gpg.d/cassandra.gpg
echo "deb https://debian.cassandra.apache.org 41x main" | sudo tee -a /etc/apt/sources.list.d/cassandra.list
sudo apt update
sudo apt install cassandra -y
```

> Cassandra 4.1 n'est pas compatible avec Java 17. Si nécessaire, installer Java 11 en parallèle et configurer `JAVA_HOME` pour Cassandra.

### Démarrer Cassandra

```bash
sudo systemctl enable cassandra
sudo systemctl start cassandra
```

### Vérifier le statut

```bash
nodetool status
```

Résultat attendu :

```
Datacenter: datacenter1
=======================
Status=Up/Down
|/ State=Normal/Leaving/Joining/Moving
--  Address    Load        Tokens  Owns (effective)  Host ID                               Rack
UN  127.0.0.1  120.35 KiB  16      100.0%            614d7f2d-f615-48da-bc19-09e2ca39a700  rack1
```

---

## 5. Installation de TheHive

### Télécharger le paquet

```bash
cd /tmp
wget -O /tmp/thehive.deb https://thehive.download.strangebee.com/5.7/deb/thehive_5.7.2-1_all.deb
```

### Installer

```bash
sudo dpkg -i /tmp/thehive.deb
```

### Configurer TheHive

```bash
sudo nano /etc/thehive/application.conf
```

```hocon
db.janusgraph {
  storage.backend = cql
  storage.hostname = ["127.0.0.1"]
  storage.cql.cluster-name = thp
  storage.cql.keyspace = thehive

  index.search {
    backend = elasticsearch
    hostname = ["127.0.0.1"]
    index-name = thehive
    elasticsearch.http.port = 9200
  }
}

play.http.secret.key = "PEgpNCoMOCOkimKTULyzZeQ0W3DbOLQRKV5ZHKzDcidotHxLsOamWqMgwBYQS7g0pjg7ryXPQMkIrTdZmgU8Vw=="

application.baseUrl = "http://192.168.100.80:9000"
```

### Créer le répertoire de stockage

```bash
sudo mkdir -p /opt/thp/thehive/files
sudo chown -R thehive:thehive /opt/thp/thehive/files
```

### Démarrer TheHive

```bash
sudo systemctl restart thehive
sudo systemctl status thehive
```

### Vérifier le port d'écoute

```bash
ss -tulpn | grep 9000
```

### Ouvrir le port dans le pare-feu si nécessaire

```bash
sudo ufw allow 9000/tcp
```

### Accéder à l'interface web

```
http://192.168.100.80:9000
```

Identifiants initiaux :

```
username : admin@thehive.local
password : secret
```

### Vérification des logs

```bash
sudo journalctl -u thehive -f
sudo journalctl -u opensearch -f
sudo journalctl -u cassandra -f
```

> Faire un snapshot de la VM à ce stade.

---

## 6. Création de l'organisation et du compte SOC

Le compte `admin` par défaut ne dispose pas des permissions nécessaires pour créer des alertes via l'API. Il faut créer une organisation dédiée et un compte de service.

### Générer une clé API admin

Depuis l'interface web : icône admin → Settings → API key → Create.

### Créer l'organisation SOC

```bash
curl -XPOST \
  -H "Authorization: Bearer <ADMIN_API_KEY>" \
  -H "Content-Type: application/json" \
  http://localhost:9000/api/v1/organisation \
  -d '{"name": "SOC", "description": "SOC Team"}'
```

### Créer le compte de service Wazuh

```bash
curl -XPOST \
  -H "Authorization: Bearer <ADMIN_API_KEY>" \
  -H "Content-Type: application/json" \
  http://localhost:9000/api/v1/user \
  -d '{
    "login": "wazuh@soc.local",
    "name": "Wazuh SOC",
    "profile": "org-admin",
    "organisation": "SOC",
    "password": "Wazuh@SOC2024!"
  }'
```

Se connecter avec ce compte et générer une nouvelle clé API depuis l'interface. Cette clé sera utilisée dans tous les scripts d'intégration.

---

## 7. Intégration Wazuh → TheHive

### Architecture de déclenchement

```
Wazuh détecte un événement
        ↓
Crée une alerte avec ID unique
        ↓
Script custom-thehive.py s'exécute
        ↓
Envoi à l'API TheHive
        ↓
Création d'une alerte (et d'un case si severity >= HIGH)
```

### Fichier wrapper (obligatoire pour Wazuh)

Wazuh exige un fichier shell sans extension du même nom que le script Python dans le dossier `integrations` :

```bash
sudo nano /var/ossec/integrations/custom-thehive
```

```sh
#!/bin/sh

WPYTHON_BIN="../framework/python/bin/python3"
SCRIPT_PATH="$0.py"
DIR_NAME="$(cd "$(dirname "$0")"; pwd -P)"
SCRIPT="$DIR_NAME/${SCRIPT_PATH##*/}"

case "$1" in
    debug)
        exec ${DIR_NAME}/${WPYTHON_BIN} ${SCRIPT} "$2"
        ;;
    *)
        exec ${DIR_NAME}/${WPYTHON_BIN} ${SCRIPT} "$@"
        ;;
esac
```

### Script principal

```bash
sudo nano /var/ossec/integrations/custom-thehive.py
```

```python
#!/usr/bin/env python3

import sys
import json
import requests
import time

# ==========================
# Configuration TheHive
# ==========================

THEHIVE_ALERT_URL = "http://192.168.100.80:9000/api/v1/alert"
THEHIVE_CASE_URL  = "http://192.168.100.80:9000/api/v1/case"
API_KEY = "Otvrg1He9gwUbw+OYH7McAnfIrj6FkQE"

# ==========================
# Chargement de l'alerte
# ==========================

alert_file = sys.argv[1]

with open(alert_file) as f:
    alert = json.load(f)

# ==========================
# Extraction des champs
# ==========================

title = alert['rule']['description']
level = int(alert['rule']['level'])

# ==========================
# Mapping de sévérité
# ==========================

if level <= 4:
    severity = 1       # LOW
elif level <= 7:
    severity = 2       # MEDIUM
elif level <= 11:
    severity = 3       # HIGH
else:
    severity = 4       # CRITICAL

# ==========================
# Référence unique
# ==========================

source_ref = f"{alert['id']}-{int(time.time())}"

# ==========================
# Headers
# ==========================

headers = {
    "Authorization": f"Bearer {API_KEY}",
    "Content-Type": "application/json"
}

# ==========================
# Création de l'alerte TheHive
# ==========================

alert_data = {
    "title": title,
    "description": json.dumps(alert, indent=2),
    "severity": severity,
    "source": "Wazuh",
    "sourceRef": source_ref,
    "type": "external",
    "artifacts": []
}

response = requests.post(THEHIVE_ALERT_URL, headers=headers, json=alert_data)

print("\n========== ALERT ==========")
print(response.status_code)
print(response.text)

# ==========================
# Création automatique d'un case (severity HIGH ou CRITICAL)
# ==========================

if severity >= 3:

    case_data = {
        "title": f"[SOC] {title}",
        "description": json.dumps(alert, indent=2),
        "severity": severity,
        "tlp": 2,
        "tags": ["wazuh", "soc", "auto-created"]
    }

    case_response = requests.post(THEHIVE_CASE_URL, headers=headers, json=case_data)

    print("\n========== CASE ==========")
    print(case_response.status_code)
    print(case_response.text)
```

### Permissions et dépendances

```bash
sudo chmod +x /var/ossec/integrations/custom-thehive
sudo chmod +x /var/ossec/integrations/custom-thehive.py
sudo apt install python3-requests -y
```

### Déclarer l'intégration dans Wazuh

Ajouter dans `/var/ossec/etc/ossec.conf` :

```xml
<ossec_config>
  <integration>
    <name>custom-thehive</name>
    <hook_url>http://192.168.100.80:9000</hook_url>
    <level>10</level>
    <alert_format>json</alert_format>
  </integration>
</ossec_config>
```

Redémarrer le manager :

```bash
sudo systemctl restart wazuh-manager
```

---

## 8. Test et validation

### Créer un fichier d'alerte de test

```bash
nano test_alert.json
```

```json
{
  "id": "100001",
  "rule": {
    "level": 12,
    "description": "Test SOC Alert"
  }
}
```

### Exécuter le script manuellement

```bash
python3 /var/ossec/integrations/custom-thehive.py test_alert.json
```

Résultat attendu :

```
========== ALERT ==========
201
{"_id":"~4464856","_type":"Alert","_createdBy":"wazuh@soc.local","title":"Test SOC Alert","severity":4,"severityLabel":"CRITICAL","status":"New",...}

========== CASE ==========
201
{"_id":"~...","title":"[SOC] Test SOC Alert","severity":4,...}
```

---

## 9. Comportement en production

| Niveau Wazuh | Sévérité TheHive | Alerte créée | Case créé automatiquement |
| ------------ | ---------------- | :----------: | :-----------------------: |
| 1 – 4        | LOW (1)          | Oui          | Non                       |
| 5 – 7        | MEDIUM (2)       | Oui          | Non                       |
| 8 – 11       | HIGH (3)         | Oui          | Oui                       |
| 12+          | CRITICAL (4)     | Oui          | Oui                       |

Les alertes réelles du SIEM remontent automatiquement dans TheHive dès que le niveau configuré (`<level>10</level>`) est atteint.