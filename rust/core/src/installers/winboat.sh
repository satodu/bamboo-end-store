#!/bin/bash
set -e

echo "=== Instalação Otimizada do WinBoat para o Bamboo ==="

# 1. Checar KVM
echo "[1/5] Verificando aceleração por hardware (KVM)..."
if [ ! -e /dev/kvm ]; then
    echo "AVISO: /dev/kvm não foi detectado no sistema."
    echo "O WinBoat requer aceleração KVM (AMD-V ou Intel VT-x) ativada na sua BIOS/UEFI."
    echo "A instalação continuará, mas lembre-se de ativar a virtualização na BIOS para executar os containers."
else
    echo "Suporte a KVM detectado com sucesso (/dev/kvm presente)."
fi

# 2. Configurar Docker
echo "[2/5] Configurando Docker e Docker Compose..."
if ! pacman -Qq docker &>/dev/null || ! pacman -Qq docker-compose &>/dev/null; then
    echo "Instalando pacotes docker e docker-compose via pacman..."
    pkexec pacman -S --needed --noconfirm docker docker-compose
else
    echo "Docker já está instalado."
fi

# Criar grupo docker e adicionar usuário
if ! getent group docker >/dev/null; then
    echo "Criando grupo docker..."
    pkexec groupadd docker
fi

ACTUAL_USER="${SUDO_USER:-$USER}"
if [ -n "$ACTUAL_USER" ] && [ "$ACTUAL_USER" != "root" ]; then
    if ! id -nG "$ACTUAL_USER" | grep -qw "docker"; then
        echo "Adicionando usuário $ACTUAL_USER ao grupo docker..."
        pkexec usermod -aG docker "$ACTUAL_USER"
    fi
fi

echo "Ativando e iniciando serviços docker e docker.socket..."
pkexec systemctl enable --now docker.service
pkexec systemctl enable --now docker.socket

if systemctl is-active --quiet firewalld; then
    echo "Configurando portas no firewalld (8006/tcp e 3389/tcp)..."
    pkexec firewall-cmd --permanent --zone=trusted --add-interface=docker0 2>/dev/null || true
    pkexec firewall-cmd --permanent --add-port=8006/tcp 2>/dev/null || true
    pkexec firewall-cmd --permanent --add-port=3389/tcp 2>/dev/null || true
    pkexec firewall-cmd --reload 2>/dev/null || true
fi

# 3. FreeRDP via Flatpak
echo "[3/5] Verificando cliente FreeRDP..."
if command -v flatpak &>/dev/null; then
    echo "Instalando/verificando com.freerdp.FreeRDP via Flatpak..."
    flatpak install -y --noninteractive flathub com.freerdp.FreeRDP 2>/dev/null || true
else
    echo "Aviso: Flatpak não encontrado. O WinBoat pode requerer o FreeRDP instalado separadamente."
fi

# 4. Módulos de rede do Kernel
echo "[4/5] Configurando módulos do kernel (ip_tables e iptable_nat)..."
echo -e "ip_tables\niptable_nat" | pkexec tee /etc/modules-load.d/iptables.conf >/dev/null
pkexec modprobe ip_tables 2>/dev/null || true
pkexec modprobe iptable_nat 2>/dev/null || true

# 5. Instalar o aplicativo WinBoat
echo "[5/5] Instalando pacote WinBoat..."
if pacman -Qq winboat-bin &>/dev/null || pacman -Qq winboat &>/dev/null; then
    echo "WinBoat já se encontra instalado no sistema."
else
    if command -v paru &>/dev/null; then
        echo "Instalando winboat-bin via paru..."
        paru --sudo pkexec --skipreview --noconfirm --needed -S winboat-bin
    elif command -v yay &>/dev/null; then
        echo "Instalando winboat-bin via yay..."
        yay --sudo pkexec --noedit --noconfirm --needed -S winboat-bin
    else
        echo "AUR helper não disponível. Baixando AppImage oficial do WinBoat..."
        TAG=$(curl -s "https://api.github.com/repos/winboat-org/winboat/releases/latest" | grep -oP '"tag_name": "\K(.*)(?=")')
        VER="${TAG#v}"
        if [ -n "$TAG" ]; then
            TMPDIR=$(mktemp -d)
            cd "$TMPDIR"
            curl -LO "https://github.com/winboat-org/winboat/releases/download/$TAG/winboat-$VER-x86_64.AppImage"
            chmod +x "winboat-$VER-x86_64.AppImage"
            pkexec mv "winboat-$VER-x86_64.AppImage" /usr/local/bin/winboat
            cd /
            rm -rf "$TMPDIR"
        fi
    fi
fi

echo "========================================================"
echo "Instalação do WinBoat concluída com sucesso!"
echo "Caso tenha sido adicionado ao grupo docker nesta sessão,"
echo "pode ser necessário reiniciar ou encerrar a sessão (logout)"
echo "para atualizar as permissões do usuário."
echo "========================================================"
