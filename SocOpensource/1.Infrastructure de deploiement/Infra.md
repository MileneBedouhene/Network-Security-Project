# Installation, Configuration et Déploiement des Agents

---

## 1. Allocation des ressources

| Machine VM                      | État      | RAM    | vCPUs    | Stockage (SSD) | Rôle                                              |
| ------------------------------- | --------- | ------ | -------- | -------------- | ------------------------------------------------- |
| **Kali Linux**                  | Existante | 8 Go   | 4 Cores  | 80 Go          | Attaquant principal & machine de travail          |
| **Windows 10**                  | Existante | 4 Go   | 2 Cores  | 60 Go          | Poste client cible (Agent Wazuh + Sysmon)         |
| **Windows AD**                  | Existante | 4 Go   | 4 Cores  | 60 Go          | Contrôleur de domaine (génération de logs critiques) |
| **Ubuntu Server (Wazuh)**       | À créer   | 8 Go   | 6 Cores  | 100 Go         | Serveur Wazuh (Manager + Indexer + Dashboard)     |
| **Debian (Core Plus)**          | À créer   | 8 Go   | 6 Cores  | 100 Go         | —                                                 |
| **Ubuntu Server (Appli Vulnérable)** | À créer | 8 Go  | 6 Cores  | 100 Go         | —                                                 |
| **FortiWeb**                    | À créer   | 8 Go   | 6 Cores  | 100 Go         | Web Application Firewall                          |
| **Ubuntu Server (TheHive)**     | À créer   | 8 Go   | 6 Cores  | 100 Go         | Gestion des incidents                             |
| **TOTAL**                       | —         | 56 Go  | 44 Cores | 740 Go         | —                                                 |

**Remarques :**

- Le serveur Wazuh est le composant critique du lab — lui allouer plus de ressources améliore la détection.
- Active Directory génère beaucoup de logs et nécessite une bonne capacité CPU.
- Le stockage SSD est recommandé pour de meilleures performances globales.

---

## 2. Installation des machines virtuelles

### 2.1 Kali Linux

Installation standard. VM principale déjà existante.
Version : **Kali 2025.4**

### 2.2 Windows 10

Installation basique. VM déjà existante.

### 2.3 Ubuntu Server

Installation basique.
Version : **Ubuntu Server 24.04.3 LTS**

---

## 3. Configuration réseau

Toutes les machines virtuelles sont configurées en mode **Host-Only**, ce qui permet une communication directe entre elles sans accès Internet (sauf exception explicitée ci-dessous).

### Plage d'adressage

| Machine VM                        | Adresse IP                                                            |
| --------------------------------- | --------------------------------------------------------------------- |
| **Ubuntu Server (Wazuh)**         | 192.168.100.10                                                        |
| **Windows 10**                    | 192.168.100.20                                                        |
| **Windows AD**                    | 192.168.100.30                                                        |
| **Core Plus (Debian)**            | 192.168.100.40                                                        |
| **Kali Linux**                    | 192.168.100.50                                                        |
| **Appli Vulnérable**              | 192.168.100.60                                                        |
| **FortiWeb (WAF)**                | 192.168.100.70:8080 (WAF) / 192.168.100.70:80 (Application Vulnérable) |
| **TheHive**                       | 192.168.100.80                                                        |

---

### 3.1 Ubuntu Server (Wazuh)

Cette machine nécessite un accès Internet ponctuel (téléchargement de paquets). Une seconde interface réseau est donc ajoutée en NAT en plus de l'interface Host-Only.

Modifier le fichier de configuration Netplan :

```bash
sudo nano /etc/netplan/00-installer-config.yaml
```

```yaml
network:
  version: 2
  ethernets:
    ens33:
      dhcp4: true
    ens37:
      dhcp4: no
      addresses:
        - 192.168.100.10/24
```

Appliquer la configuration :

```bash
sudo netplan apply
```

---

### 3.2 Windows 10

Accéder au **Panneau de configuration > Centre Réseau et partage > Modifier les paramètres de la carte**, puis assigner l'adresse statique `192.168.100.20` avec le masque `255.255.255.0`.

---

### 3.3 Windows AD

Même procédure que Windows 10. Assigner l'adresse statique `192.168.100.30` avec le masque `255.255.255.0`.

---

### 3.4 Kali Linux

Modifier le fichier des interfaces réseau :

```bash
sudo nano /etc/network/interfaces
```

```bash
auto eth0
iface eth0 inet static
    address 192.168.100.50
    netmask 255.255.255.0
```

Appliquer la configuration :

```bash
sudo systemctl restart networking
```

---

### 3.5 Vérification

Une fois toutes les adresses assignées, vérifier la connectivité entre les machines avec des pings croisés. Tous les pings doivent répondre correctement.

---

## 4. Installation de Wazuh (Ubuntu Server)

### 4.1 Préparation de la machine

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install curl apt-transport-https unzip -y
```

### 4.2 Téléchargement du script officiel

```bash
curl -sO https://packages.wazuh.com/4.7/wazuh-install.sh
chmod +x wazuh-install.sh
```

### 4.3 Installation all-in-one

Cette commande installe le **Manager**, l'**Indexer** et le **Dashboard** en une seule opération :

```bash
sudo ./wazuh-install.sh -a
```

### 4.4 Vérification des services

Après l'installation, vérifier que les trois composants ont démarré correctement :

```bash
sudo systemctl status wazuh-manager
sudo systemctl status wazuh-indexer
sudo systemctl status wazuh-dashboard
```

L'interface web est ensuite accessible depuis n'importe quelle machine du réseau à l'adresse :

```
https://192.168.100.10
```

---

## 5. Installation et configuration des agents Wazuh

### 5.1 Sur Windows 10 et Windows AD

Depuis le Dashboard Wazuh, naviguer vers **Agents > Deploy new agent**, sélectionner **Windows** comme système d'exploitation, renseigner l'adresse du manager (`192.168.100.10`), puis exécuter la commande générée dans un terminal PowerShell avec les droits administrateur.

Vérifier que l'agent apparaît comme **actif** dans le Dashboard une fois installé.

### 5.2 Sur Linux (Kali / Ubuntu)

Depuis le Dashboard Wazuh, naviguer vers **Agents > Deploy new agent**, sélectionner **Linux (DEB/RPM)** selon la distribution, renseigner l'adresse du manager, puis exécuter les commandes générées sur la machine cible :

```bash
# Exemple pour une distribution Debian/Ubuntu
curl -s https://packages.wazuh.com/key/GPG-KEY-WAZUH | apt-key add -
echo "deb https://packages.wazuh.com/4.x/apt/ stable main" | tee /etc/apt/sources.list.d/wazuh.list
apt-get update && apt-get install wazuh-agent

# Configurer le manager
sed -i 's/MANAGER_IP/192.168.100.10/' /var/ossec/etc/ossec.conf

# Démarrer et activer l'agent
systemctl daemon-reload
systemctl enable wazuh-agent
systemctl start wazuh-agent
```

