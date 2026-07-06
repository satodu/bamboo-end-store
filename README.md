# 🐼 Bamboo End Store (Community Edition)

<p align="center">
  <img src="public/logo-without-background.png" width="350" alt="Bamboo End Store Logo">
</p>

<p align="center">
    <a href="https://github.com/satodu/panda-bamboo-store/releases">
        <img src="https://img.shields.io/github/v/release/satodu/panda-bamboo-store?style=for-the-badge&color=2e7d32" alt="Release">
    </a>
    <a href="LICENSE">
        <img src="https://img.shields.io/github/license/satodu/panda-bamboo-store?style=for-the-badge&color=2e7d32" alt="License">
    </a>
    <a href="https://satodu.github.io/panda-bamboo-store/">
        <img src="https://img.shields.io/badge/Official_Website-Visit_Site-2e7d32?style=for-the-badge&logo=googlechrome&logoColor=white" alt="Website">
    </a>
</p>

<p align="center">
  <b>🌐 Check the official website: <a href="https://satodu.github.io/panda-bamboo-store/">satodu.github.io/panda-bamboo-store/</a></b>
</p>

A modern, high-performance, and beautiful community-driven store for **Bamboo OS/Arch Linux**, built with **Laravel**, **Livewire (Volt)**, and **NativePHP**.

> [!IMPORTANT]
> **UNOFFICIAL FAN PROJECT**: This application is not an official product of any Linux distribution team. It is a fan-made project created by **Panda** to enhance the software management experience on Arch Linux and derivatives.

---

## 📸 Visual Preview

<p align="center">
  <img src="resources/screenshots/home.png" width="800" alt="Home Screen">
</p>

<div align="center">
  <img src="resources/screenshots/details.png" width="48%" alt="Package Details">
  <img src="resources/screenshots/search.png" width="48%" alt="Search Results">
</div>

<br>

<div align="center">
  <img src="resources/screenshots/appimages.png" width="48%" alt="AppImages Manager">
  <img src="resources/screenshots/settings.png" width="48%" alt="Settings Menu">
</div>

---

## ✨ Features

- **⚙️ AppImage Management (Gear Lever Style)**: Integrated manager to integrate, launch, and uninstall AppImages. Automatically handles icon extraction and desktop menu entries.
- **📦 Flatpak Support** *(New in v1.1.1)*: Browse, install, update, and remove Flatpak applications directly from the store. Enable or disable Flatpak integration at any time from Settings.
- **🚀 Performance-First**: Intelligent caching system for lightning-fast search results.
- **💎 Shadcn UI Aesthetic**: Clean, professional, and minimalist design based on the Zinc theme.
- **📦 AUR Integration**: Search and install community packages directly from the AUR via `yay`.
- **📑 Real Pagination**: Navigate through thousands of packages easily with a robust pagination system.
- **⚙️ Configurable**: Toggle repositories, set search limits, and manage your preferences in a dedicated settings menu.
- **🖥️ Native Experience**: Runs as a native Linux desktop application thanks to **NativePHP (Electron)**.
- **📊 System Info**: Real-time display of Hostname, Kernel, and OS info in the sidebar.

---

## 📦 Installation

### Recommended: Arch User Repository (AUR)

If you are using **Arch Linux** or derivatives, the easiest way to install is via AUR. You can use any AUR helper like `paru` or `yay`:

```bash
paru -S bamboo-end-store-bin
```
*or*
```bash
yay -S bamboo-end-store-bin
```

---

### Development Setup

If you want to contribute or run the latest development version:

1. **Clone the repository:**
   ```bash
   git clone https://github.com/satodu/panda-bamboo-store.git
   cd panda-bamboo-store
   ```

2. **Install dependencies:**
   ```bash
   composer install
   npm install
   ```

3. **Configure environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Run the development server:**
   ```bash
   php artisan native:serve
   ```

---

## 📦 Distribution

To generate a portable version for yourself or others:

```bash
php artisan native:build linux
```

This will generate an **AppImage** and a **.deb** package in the `dist` folder.

---

## 🛡️ License

This project is open-source and available under the [MIT License](LICENSE).

## 🤝 Credits

Created with ❤️ by **Panda**.
Inspired by the strength and versatility of **Bamboo**.
Special thanks to the **NativePHP** and **Laravel** communities.
