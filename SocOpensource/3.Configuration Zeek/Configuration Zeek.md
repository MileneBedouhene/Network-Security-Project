# Installation et Configuration de Zeek (Ubuntu Server 24.04)

---

## Introduction

Zeek est un outil de **Network Security Monitoring (NSM)** permettant d'analyser le trafic réseau de manière comportementale.

Dans ce lab SOC :

- **Zeek** — Détection de comportements (Ping Sweep, Port Scan, DNS Exfiltration, Reverse Shell)
- **Wazuh** — Centralisation et corrélation des alertes

---

## Problème de compatibilité avec Ubuntu 24.04

Zeek n'est pas disponible nativement dans les dépôts Ubuntu 24.04. La solution retenue est d'utiliser le dépôt **Ubuntu 22.04**, qui reste compatible.

---

## 1. Installation de Zeek

### Ajouter la clé GPG

```bash
curl -fsSL https://download.opensuse.org/repositories/security:zeek/xUbuntu_22.04/Release.key | sudo gpg --dearmor -o /usr/share/keyrings/zeek.gpg
```

### Ajouter le dépôt

```bash
echo "deb [signed-by=/usr/share/keyrings/zeek.gpg] http://download.opensuse.org/repositories/security:/zeek/xUbuntu_22.04/ /" | sudo tee /etc/apt/sources.list.d/zeek.list
```

### Mettre à jour et installer

```bash
sudo apt update
sudo apt install zeek -y
```

> Pendant l'installation, un écran de configuration Postfix peut apparaître. Choisir **No configuration**.

---

## 2. Vérification de l'installation

```bash
/opt/zeek/bin/zeek --version
```

Résultat attendu :

```
Zeek version 8.0.5
```

---

## 3. Ajouter Zeek au PATH

```bash
nano ~/.bashrc
```

Ajouter en fin de fichier :

```bash
export PATH=$PATH:/opt/zeek/bin
```

Appliquer :

```bash
source ~/.bashrc
```

---

## 4. Identifier l'interface réseau

```bash
ip a
```

Noter le nom de l'interface à surveiller (ex : `ens33`, `ens37`).

---

## 5. Configuration de Zeek

```bash
sudo nano /opt/zeek/etc/node.cfg
```

```ini
[zeek]
type=standalone
host=localhost
interface=ens33
```

> Remplacer `ens33` par l'interface réseau réelle identifiée à l'étape précédente.

---

## 6. Lancer Zeek

```bash
cd /opt/zeek/bin
sudo ./zeekctl deploy
```

---

## 7. Vérifier le statut

```bash
sudo ./zeekctl status
```

Résultat attendu :

```
running
```

---

## 8. Vérifier les logs générés

```bash
ls /opt/zeek/logs/current/
```

Logs principaux :

- `conn.log` — connexions réseau
- `icmp.log` — trafic ICMP
- `dns.log` — requêtes DNS

---

## 9. Test de fonctionnement

Depuis une autre machine du réseau :

```bash
ping <IP_serveur>
```

Vérifier la détection côté Zeek :

```bash
cat /opt/zeek/logs/current/icmp.log
```

Les champs `src_ip` et `dest_ip` doivent apparaître dans la sortie.

---

## 10. Redémarrage en cas de problème

```bash
cd /opt/zeek/bin
sudo ./zeekctl stop
sudo ./zeekctl deploy
```

---

## 11. Démarrage automatique via systemd

Pour que Zeek démarre automatiquement avec l'interface en mode promiscuous, créer un fichier de service systemd dédié.

### Créer le fichier de service

```bash
sudo nano /etc/systemd/system/zeek-custom.service
```

```ini
[Unit]
Description=Zeek Custom IDS Service
After=network.target

[Service]
Type=simple
ExecStartPre=/usr/bin/ip link set ens37 promisc on
ExecStart=/usr/bin/stdbuf -oL /opt/zeek/bin/zeek -i ens37 -C /opt/zeek/share/zeek/site/local.zeek
StandardOutput=append:/var/log/zeek_soc.log
StandardError=inherit
Restart=always

[Install]
WantedBy=multi-user.target
```

> Remplacer `ens37` par l'interface réellement utilisée.

### Activer et démarrer le service

```bash
sudo systemctl daemon-reload
sudo systemctl enable zeek-custom
sudo systemctl start zeek-custom
```

---

## 12. Règles de détection personnalisées

### Emplacement des règles

Les règles Zeek sont placées dans :

```
/opt/zeek/share/zeek/site/
```

### Référencer les fichiers de règles

Chaque fichier de règles doit être chargé dans `local.zeek` en ajoutant à la fin du fichier :

```bash
sudo nano /opt/zeek/share/zeek/site/local.zeek
```

```zeek
@load <nom_du_fichier_de_regles>
```

### Appliquer les modifications

```bash
cd /opt/zeek/bin
sudo ./zeekctl deploy
```

---

## 13. Fichier de règles personnalisées — `soc-alerts.zeek`

Ce fichier centralise toutes les règles de détection comportementale du lab. Il couvre le Ping Flood, le Ping Sweep, le Port Scan agressif, l'énumération de services, le Reverse Shell et la DNS Exfiltration.

Créer le fichier :

```bash
sudo nano /opt/zeek/share/zeek/site/soc-alerts.zeek
```

Contenu complet :

```zeek
# ==============================================================
# SOC Lab — Règles de détection comportementale Zeek
# ==============================================================

# --- Tables avec expiration ---
global flood_count: table[addr, addr] of count &default=0 &write_expire=5sec;
global victims_sweep: table[addr] of set[addr] &write_expire=10sec;

# --- Ping Flood & Ping Sweep ---
event icmp_echo_request(c: connection, info: icmp_info, id: count, seq: count, payload: string)
{
    local src = c$id$orig_h;
    local dst = c$id$resp_h;
    local p_size = c$orig$size;

    # Ping Flood : 100 pings vers la même cible en moins de 5 secondes
    flood_count[src, dst] += 1;
    if ( flood_count[src, dst] == 100 )
    {
        print fmt("ALERTE_PING_FLOOD | Attaquant: %s | Victime: %s | Info: 100 pings en < 5s | Taille: %d",
                  src, dst, p_size);
        delete flood_count[src, dst];
    }

    # Ping Sweep : un même source pingue 3 hôtes distincts ou plus
    if ( src !in victims_sweep ) victims_sweep[src] = set();
    add victims_sweep[src][dst];
    if ( |victims_sweep[src]| >= 3 )
    {
        print fmt("ALERTE_PING_SWEEP | Attaquant: %s | Cibles_Detectees: %d | Derniere_Cible: %s",
                  src, |victims_sweep[src]|, dst);
        delete victims_sweep[src];
    }
}

# --- Port Scan agressif ---
global scanned_ports: table[addr] of set[port] &write_expire=10sec;
global already_alerted: table[addr] of bool &write_expire=30sec;

event connection_established(c: connection)
{
    local src = c$id$orig_h;
    local dst = c$id$resp_h;
    local d_port = c$id$resp_p;
    local port_num = port_to_count(d_port);

    # Ignorer les ports courants
    if ( port_num == 80 || port_num == 443 || port_num == 53 )
        return;

    if ( src !in scanned_ports )
        scanned_ports[src] = set();
    add scanned_ports[src][d_port];

    # Alerte à partir de 15 ports distincts scannés en 10 secondes
    if ( |scanned_ports[src]| >= 15 )
    {
        if ( src !in already_alerted )
        {
            print fmt("ALERTE_PORT_SCAN | Attaquant: %s | Victime: %s | Ports_Scannes: %d",
                      src, dst, |scanned_ports[src]|);
            already_alerted[src] = T;
        }
    }
}

# --- Enumération de services (scan -sV) ---
global enum_ports: table[addr] of set[port] &write_expire=20sec;
global enum_alerted: table[addr] of bool &write_expire=30sec;

event connection_state_remove(c: connection)
{
    local src = c$id$orig_h;
    local dst = c$id$resp_h;
    local d_port = c$id$resp_p;
    local port_num = port_to_count(d_port);

    # Ignorer DNS
    if ( port_num == 53 )
        return;

    # Connexions très courtes typiques d'un scan de versions
    if ( c$duration < 3sec )
    {
        if ( src !in enum_ports )
            enum_ports[src] = set();
        add enum_ports[src][d_port];

        if ( |enum_ports[src]| >= 8 )
        {
            if ( src !in enum_alerted )
            {
                print fmt("ALERTE_ENUM_SERVICES | Attaquant: %s | Victime: %s | Services_Enumeres: %d",
                          src, dst, |enum_ports[src]|);
                enum_alerted[src] = T;
            }
        }
    }

    # --- Reverse Shell ---
    # Connexion longue (> 15 secondes) sur un port non standard
    if ( port_num == 80 || port_num == 443 || port_num == 53 || port_num == 123 )
        return;

    if ( c$duration > 15sec )
    {
        print fmt("ALERTE_REVERSE_SHELL | Attaquant: %s | Victime: %s | Port: %d | Duree: %fs",
                  src, dst, port_num, c$duration);
    }
}

# --- DNS Exfiltration ---
# Requête DNS dont le nom de domaine dépasse 20 caractères
event dns_request(c: connection, msg: dns_msg, query: string, qtype: count, qclass: count)
{
    local src = c$id$orig_h;
    local dst = c$id$resp_h;

    if ( query != "" && |query| > 20 )
    {
        print fmt("ALERTE_DNS_EXFIL | Attaquant: %s | Serveur_DNS: %s | Requete: %s",
                  src, dst, query);
    }
}
```

### Charger le fichier dans local.zeek

```bash
sudo nano /opt/zeek/share/zeek/site/local.zeek
```

Ajouter à la fin :

```zeek
@load soc-alerts
```

Appliquer :

```bash
cd /opt/zeek/bin
sudo ./zeekctl deploy
```

---

## 14. Intégration avec Wazuh

### Déclarer le fichier de logs Zeek dans l'agent Wazuh

```bash
sudo nano /var/ossec/etc/ossec.conf
```

Ajouter dans la section `<ossec_config>` :

```xml
<localfile>
    <log_format>syslog</log_format>
    <location>/var/log/zeek_soc.log</location>
</localfile>
```

### Redémarrer l'agent

```bash
sudo systemctl restart wazuh-agent
```

Wazuh collecte désormais les alertes générées par Zeek et les intègre au pipeline de corrélation SOC.

---

## Alertes couvertes

| Alerte               | Déclencheur                                              | Niveau attendu |
| -------------------- | -------------------------------------------------------- | -------------- |
| `ALERTE_PING_FLOOD`  | 100 pings vers le même hôte en moins de 5 secondes      | Low            |
| `ALERTE_PING_SWEEP`  | Ping vers 3 hôtes distincts ou plus en moins de 10 secondes | Low        |
| `ALERTE_PORT_SCAN`   | 15 ports distincts contactés en moins de 10 secondes    | Low            |
| `ALERTE_ENUM_SERVICES` | 8 connexions courtes sur ports variés en moins de 20 secondes | Low      |
| `ALERTE_REVERSE_SHELL` | Connexion non standard maintenue plus de 15 secondes  | Critical       |
| `ALERTE_DNS_EXFIL`   | Requête DNS avec nom de domaine supérieur à 20 caractères | Critical      |