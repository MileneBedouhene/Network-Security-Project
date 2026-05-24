# 🛡️ Lab Cybersécurité Docker — Mini-Projet 4ème SSI

Adaptation Docker du mini-projet « Sécurité des Réseaux et Infrastructures » (60 jours), conçue pour fonctionner sur une machine à ressources limitées (laptop ~8 Go RAM).

---

# 🎯 Objectif de cette adaptation

Reproduire le socle pédagogique principal du lab pfSense + 2× FortiGate + Kali, mais en utilisant uniquement Docker et des conteneurs Linux légers.

Le périmètre validé couvre :

- la segmentation réseau
- le routage
- le NAT
- le DNS/NTP
- le VPN IPsec
- le DHCP
- le proxy Squid
- HAProxy
- Suricata en IDS
- le monitoring
- le hardening avancé de Phase 2
- la haute disponibilité keepalived / conntrackd
- la remédiation SSH Phase 3
- la Phase 4 complète en mode SOC / SIEM léger

---

# 📦 Équivalences de l’architecture

| Composant original | Remplacement Docker | Fonction |
|---|---|---|
| pfSense | `fw-isp` + `fw-isp-2` (Debian + iptables + dnsmasq + chrony + HAProxy + keepalived) | Firewall ISP HA, DNS, NTP, publication HTTP |
| FortiGate FW_CLIENT | `fw-client` + `fw-client-2` (Debian + iptables + strongSwan + dnsmasq + squid + keepalived + conntrackd) | FW client HA, VPN IPsec, DHCP, filtrage web |
| FortiGate FW_SERVER | `fw-server` + `fw-server-2` (Debian + iptables + strongSwan + chrony + keepalived + conntrackd) | FW serveur HA, VPN IPsec, NTP |
| VPN IPsec IKEv2 | strongSwan (AES-256, SHA-256, modp2048) | Équivalent pédagogique |
| Kali Linux | Image officielle `kalilinux/kali-rolling` | Identique |
| Uptime Kuma | Image officielle `louislam/uptime-kuma` | Identique |
| VLANs (Phase 2) | Réseaux Docker bridge séparés | `lan_client`, `vlan_voip`, `vlan_guest`, `lan_server`, `dmz`, `mgmt` déployés |

---

# ✅ Statut validé aujourd'hui

## Fonctionnel et vérifié

- `fw-isp`
- `fw-client`
- `fw-server`
- `client1`
- `client2`
- `voip1`
- `guest1`
- `kali`
- `webserver`
- `sshserver`
- `dmz-web`
- `internet-probe`
- `uptime-kuma`
- `log-collector`
- `loki`
- `promtail`
- `grafana`

## Vérifications réalisées

- routage entre zones
- NAT Internet
- DNS/NTP
- IPsec
- HTTP via VPN
- SSH vers `sshserver`
- HTTP DMZ
- publication web via HAProxy
- proxy Squid
- monitoring
- tests automatisés

## Fonctionnalités validées

- Les scripts actifs résolvent les interfaces dynamiquement à partir des IPs statiques du compose ; l’ordre `ethX` n’est plus une hypothèse de fonctionnement.
- La segmentation Phase 2 cœur est intégrée : VLAN VOIP, VLAN GUEST, DMZ et matrice de flux testée.
- Le hardening avancé Phase 2 est livré :
  - objets et groupes `ipset` sur `fw-client`
  - liste `blocked_domains.txt` versionnée pour Squid
  - garde-fous egress sur `fw-isp`
- La brique IDS Phase 2 est livrée :
  - Suricata tourne sur `fw-client`
  - journalisation dans `fw-client/logs/suricata/`
  - validation des alertes de laboratoire versionnées
- La brique HA Phase 2 est livrée :
  - `keepalived` maintient les VIPs historiques
  - `conntrackd` réplique les états des deux paires FortiGate-like
- La remédiation Phase 3 par défaut est livrée :
  - `sshserver` n’accepte plus ni root login ni password auth
  - `fail2ban` actif
  - SSH retiré des serveurs web
- La brique Phase 4 livrée combine :
  - `log-collector`
  - Loki
  - Promtail
  - Grafana
  afin de centraliser les journaux, exposer un backend SIEM léger, charger des règles de détection et provisionner des dashboards SOC / HA.
- Le jeu de validation couvre désormais :
  - `test-connectivity.sh`
  - `test-vlan-matrix.sh`
  - `test-policy-hardening.sh`
  - `test-suricata.sh`
  - `test-ha.sh`
  - `test-phase3-hardening.sh`
  - `test-log-centralization.sh`
  - `test-siem-phase4.sh`
  - `test-full-lab.sh`
- Les documents :
  - `PHASE2.md`
  - `PHASE3.md`
  - `PHASE4.md`
  - `SOUTENANCE.md`
  servent de support pour l’exploitation, la restitution et la démonstration finale.

## Évolutions possibles

- Wazuh
- inspection TLS dédiée
- notifications externes
- rétention longue durée au-dessus du pipeline SIEM livré

---

# 🗺️ Architecture déployée

```text
                         ┌──────────────────────┐
                         │   Internet simulé    │
                         │   (200.0.0.0/24)     │
                         └──────────┬───────────┘
                                    │
                         ┌──────────▼───────────┐
                         │       FW_ISP         │
                         │   (pfSense-like)     │
                         │  200.0.0.10 / WAN    │
                         │  10.10.0.1  / cli    │
                         │  10.20.0.1  / srv    │
                         │  192.168.99.1 / mgmt │
                         └─────┬──────────┬─────┘
              10.10.0.0/24     │          │   10.20.0.0/24
                  ┌────────────┘          └────────────┐
                  │                                    │
        ┌─────────▼───────────┐              ┌─────────▼───────────┐
        │      FW_CLIENT      │◄═══ VPN ═══►│      FW_SERVER      │
        │  10.10.0.2   /WAN   │   IPsec     │  10.20.0.2   /WAN   │
        │ 192.168.10.1 /LAN   │   IKEv2     │ 192.168.20.1 /LAN   │
        │192.168.99.10 /mgmt  │             │192.168.99.20 /mgmt  │
        └─────────┬───────────┘              └─────────┬───────────┘
                  │                                    │
         ┌────────▼────────┐                ┌──────────▼──────────┐
         │   LAN_CLIENT    │                │     LAN_SERVER      │
         │ 192.168.10.0/24 │                │  192.168.20.0/24    │
         ├─────────────────┤                ├─────────────────────┤
         │ client1   .10   │                │ webserver  .10      │
         │ client2   .11   │                │ sshserver  .11      │
         │ kali      .50   │                │                     │
         └─────────────────┘                └─────────────────────┘
