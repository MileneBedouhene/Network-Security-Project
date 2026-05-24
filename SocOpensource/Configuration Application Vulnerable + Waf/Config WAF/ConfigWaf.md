# Configuration du WAF FortiWeb et Intégration Wazuh

---

## Environnement du lab

| Composant           | Adresse IP         | Rôle                          |
| ------------------- | ------------------ | ----------------------------- |
| FortiWeb (WAF)      | 192.168.100.70     | Web Application Firewall      |
| Application Web     | 192.168.100.60     | Backend — port 80             |
| Kali Linux          | 192.168.100.50     | Attaquant / Testeur           |
| Wazuh               | 192.168.100.10     | SIEM — réception des logs     |

Version FortiWeb : **7.52**
Réseau : Host-Only VMware (`192.168.100.0/24`)

---

## Architecture de flux

```
Client (Kali 192.168.100.50)
        ↓
FortiWeb WAF (192.168.100.70)  ← Analyse et filtrage
        ↓
Application Web (192.168.100.60:80)
```

---

## 1. Vérification de la connectivité réseau

### Depuis la CLI FortiWeb

```bash
execute ping 192.168.100.50
```

Si le ping échoue depuis FortiWeb mais fonctionne depuis les autres machines, une route statique est manquante.

### Ajouter une route par défaut

```bash
config router static
    edit 1
        set gateway 192.168.100.2
        set device port1
    next
end
```

### Vérifier les routes actives

```bash
get router info routing-table all
```

---

## 2. Configuration de l'interface FortiWeb

```bash
config system interface
    edit port1
        set mode static
        set ip 192.168.100.70 255.255.255.0
        set allowaccess ping https ssh http
    next
end
```

---

## 3. Configuration du Server Pool

Aller dans **Server Objects → Server Pool → Create New** :

| Champ  | Valeur        |
| ------ | ------------- |
| Name   | `app-pool`    |
| Type   | Reverse Proxy |

Ajouter un membre dans le pool :

| Champ | Valeur           |
| ----- | ---------------- |
| IP    | 192.168.100.60   |
| Port  | 80               |

---

## 4. Configuration du Virtual Server

Aller dans **Server Objects → Virtual Server → Create New** :

| Champ            | Valeur                          |
| ---------------- | ------------------------------- |
| Type             | Reverse Proxy                   |
| Interface        | port1                           |
| Use Interface IP | Activé                          |
| Virtual IP       | 192.168.100.70 (auto-rempli)    |

---

## 5. Résolution du conflit de port (port 80)

### Problème rencontré

Lors de la création de la Server Policy avec le port `80`, l'erreur suivante apparaît :

```
The service of this port is in use
```

L'interface d'administration du FortiWeb occupait déjà le port `80`.

### Solution appliquée

Déplacer le port de l'interface d'administration de `80` vers `8080`.

Chemin : **System → Admin → Settings**

| Paramètre | Ancienne valeur | Nouvelle valeur |
| --------- | --------------- | --------------- |
| HTTP Port | 80              | 8080            |

Résultat :
- Interface d'administration : `http://192.168.100.70:8080`
- Application web via le WAF : `http://192.168.100.70` (port 80)

---

## 6. Création de la Server Policy

Aller dans **Policy → Server Policy → Create New** :

| Champ                  | Valeur                        |
| ---------------------- | ----------------------------- |
| Name                   | `Lien_WAF_App`                |
| Virtual Server         | `Frontend`                    |
| Server Pool            | `app-pool`                    |
| HTTP Service           | HTTP (port 80)                |
| Web Protection Profile | Inline Standard Protection    |
| Monitor Mode           | Désactivé (blocage actif)     |
| Status                 | Enable                        |

---

## 7. Profil de protection WAF

Profil utilisé : `Inline Standard Protection`

| Paramètre                  | Valeur        |
| -------------------------- | ------------- |
| Monitor Mode               | OFF           |
| Action sur attaque         | Alert & Deny  |
| SQL Injection              | Activé        |
| Cross-Site Scripting (XSS) | Activé        |

Attaques bloquées par défaut :

- `' OR 1=1 --` (SQL Injection)
- `<script>alert('XSS')</script>` (XSS)
- Upload de script PHP malveillant
- Tentative de path traversal (`/etc/passwd`)

---

## 8. Intégration FortiWeb vers Wazuh via Syslog

### 8.1 Configuration côté FortiWeb

Chemin : **Log & Report → Log Policy → Syslog Policy → Create New**

| Champ      | Valeur                     |
| ---------- | -------------------------- |
| Name       | `Wazuh_Server`             |
| IP Address | 192.168.100.10             |
| Port       | 514                        |
| Protocol   | UDP                        |
| Log Format | CEF (Common Event Format)  |

Ensuite dans **Log & Report → Log Config → Global Log Settings**, activer Syslog et cocher :

- Attack Log
- Event Log
- Traffic Log

### 8.2 Configuration côté Wazuh

```bash
sudo nano /var/ossec/etc/ossec.conf
```

Ajouter dans `<ossec_config>` :

```xml
<remote>
  <connection>syslog</connection>
  <port>514</port>
  <protocol>udp</protocol>
  <allowed-ips>192.168.100.70</allowed-ips>
</remote>
```

Redémarrer le manager :

```bash
sudo systemctl restart wazuh-manager
```

Vérifier que le port est ouvert :

```bash
ss -ulnp | grep 514
```

### 8.3 Vérification du flux Syslog

Capturer le trafic entrant sur le serveur Wazuh :

```bash
sudo tcpdump -i ens37 udp port 514 -A
```

Puis générer une attaque depuis Kali :

```
http://192.168.100.70/?user_id=<script>alert('SOC')</script>
```

Résultat attendu dans la capture :

```
CEF:0|Fortinet|FortiWeb|7.52|20000008|attack|alert|cat=Signature Detection
act=Alert_Deny ... cs4=Cross Site Scripting ... cs6=010000063
```

---

## 9. Décodeur et règle Wazuh personnalisés

### 9.1 Test avec wazuh-logtest

```bash
/var/ossec/bin/wazuh-logtest
```

Coller un log CEF dans l'invite. Si `No decoder matched` s'affiche, créer un décodeur personnalisé.

### 9.2 Créer le décodeur

```bash
sudo nano /var/ossec/etc/decoders/local_decoder.xml
```

```xml
<decoder name="fortiweb-custom">
  <prematch>^CEF:0|Fortinet|FortiWeb</prematch>
</decoder>

<decoder name="fortiweb-fields">
  <parent>fortiweb-custom</parent>
  <regex>src=(\S+) spt=(\d+) dst=(\S+) dpt=(\d+) request=(\S+)</regex>
  <order>srcip, srcport, dstip, dstport, request</order>
</decoder>
```

### 9.3 Créer la règle de détection

```bash
sudo nano /var/ossec/etc/rules/local_rules.xml
```

```xml
<group name="fortiweb,">
  <rule id="100700" level="12">
    <decoded_as>fortiweb-custom</decoded_as>
    <match>attack</match>
    <description>FortiWeb WAF: Tentative d'attaque bloquée détectée</description>
    <group>attack_detected,pci_dss_11.4,</group>
  </rule>
</group>
```

Redémarrer Wazuh :

```bash
sudo systemctl restart wazuh-manager
```

### 9.4 Validation

```bash
tail -f /var/ossec/logs/alerts/alerts.json
```

Résultat attendu :

```json
{
  "timestamp": "2026-05-06T10:34:44.972+0000",
  "rule": {
    "level": 12,
    "description": "FortiWeb WAF: Tentative d'attaque bloquée détectée",
    "id": "100700",
    "groups": ["fortiweb", "attack_detected"]
  },
  "decoder": { "name": "fortiweb-custom" },
  "location": "192.168.100.70"
}
```

---

## 10. Scénarios de test

### XSS

```
http://192.168.100.70/?user_id=<script>alert('SOC')</script>
```

### SQL Injection

```
http://192.168.100.70/?id=' OR 1=1 --
```

### Path Traversal

```
http://192.168.100.70/?file=../../../etc/passwd
```

Vérifier les logs bloqués dans : **Log & Report → Attack Log**

---

## 11. Récapitulatif

Chaîne de détection complète :

```
Attaque (Kali) → FortiWeb WAF → Détection/Blocage → Syslog UDP 514 → Wazuh → Alerte niveau 12
```

| Élément                               | Statut |
| ------------------------------------- | ------ |
| Connectivité réseau                   | OK     |
| Reverse Proxy WAF actif               | OK     |
| Conflit de port résolu (admin → 8080) | OK     |
| Syslog FortiWeb → Wazuh               | OK     |
| Décodeur CEF personnalisé             | OK     |
| Règle de détection niveau 12          | OK     |
| Alertes visibles dans alerts.json     | OK     |