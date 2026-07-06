<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Bamboo End Store - v{{ config('nativephp.version') }}</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                darkMode: 'class',
                theme: {
                    extend: {
                        colors: {
                            bamboo: 'var(--bamboo)',
                            background: 'var(--background)',
                            foreground: 'var(--foreground)',
                            card: {
                                DEFAULT: 'var(--card)',
                                foreground: 'var(--card-foreground)',
                            },
                            popover: {
                                DEFAULT: 'var(--popover)',
                                foreground: 'var(--popover-foreground)',
                            },
                            primary: {
                                DEFAULT: 'var(--primary)',
                                foreground: 'var(--primary-foreground)',
                            },
                            secondary: {
                                DEFAULT: 'var(--secondary)',
                                foreground: 'var(--secondary-foreground)',
                            },
                            muted: {
                                DEFAULT: 'var(--muted)',
                                foreground: 'var(--muted-foreground)',
                            },
                            accent: {
                                DEFAULT: 'var(--accent)',
                                foreground: 'var(--accent-foreground)',
                            },
                            destructive: {
                                DEFAULT: 'var(--destructive)',
                                foreground: 'var(--destructive-foreground)',
                            },
                            border: 'var(--border)',
                            input: 'var(--input)',
                            ring: 'var(--ring)',
                        },
                        borderRadius: {
                            xl: "calc(var(--radius) + 4px)",
                            lg: "var(--radius)",
                            md: "calc(var(--radius) - 2px)",
                            sm: "calc(var(--radius) - 4px)",
                        },
                        fontFamily: {
                            sans: ['Inter', 'sans-serif'],
                        },
                    }
                }
            }
        </script>
        <style>
            :root {
                --background: oklch(0.9934 0.0017 290);
                --foreground: oklch(0.2464 0.0358 290);
                --card: oklch(1.0000 0 0);
                --card-foreground: oklch(0.2464 0.0358 290);
                --popover: oklch(1.0000 0 0);
                --popover-foreground: oklch(0.2464 0.0358 290);
                --primary: oklch(0.5413 0.2466 293.01);
                --primary-foreground: oklch(0.9915 0.0116 290);
                --secondary: oklch(0.9593 0.0088 290);
                --secondary-foreground: oklch(0.4406 0.0740 290);
                --muted: oklch(0.9572 0.0053 290);
                --muted-foreground: oklch(0.5836 0.0427 290);
                --accent: oklch(0.9498 0.0187 290);
                --accent-foreground: oklch(0.4575 0.0843 290);
                --destructive: oklch(0.6356 0.2082 25.3782);
                --destructive-foreground: oklch(0.9848 0 0);
                --border: oklch(0.9161 0.0142 290);
                --input: oklch(0.9161 0.0142 290);
                --ring: oklch(0.5413 0.2466 293.01);
                --radius: 14px;
                --bamboo: #7f3f98;
            }

            .dark {
                --background: oklch(0.1396 0.0125 290);
                --foreground: oklch(0.9861 0.0023 290);
                --card: oklch(0.1700 0.0170 290);
                --card-foreground: oklch(0.9861 0.0023 290);
                --popover: oklch(0.1551 0.0146 290);
                --popover-foreground: oklch(0.9861 0.0023 290);
                --primary: oklch(0.5413 0.2466 293.01);
                --primary-foreground: oklch(0.9915 0.0116 290);
                --secondary: oklch(0.2539 0.0230 290);
                --secondary-foreground: oklch(0.9302 0.0118 290);
                --muted: oklch(0.2295 0.0197 290);
                --muted-foreground: oklch(0.7443 0.0320 290);
                --accent: oklch(0.2990 0.0371 290);
                --accent-foreground: oklch(0.9861 0.0023 290);
                --destructive: oklch(0.4344 0.1466 25.7809);
                --destructive-foreground: oklch(0.9848 0 0);
                --border: oklch(0.2852 0.0226 290);
                --input: oklch(0.2852 0.0226 290);
                --ring: oklch(0.5413 0.2466 293.01);
            }
            ::-webkit-scrollbar {
                width: 6px;
            }
            ::-webkit-scrollbar-track {
                background: var(--background);
            }
            ::-webkit-scrollbar-thumb {
                background: var(--border);
                border-radius: 10px;
            }
            ::-webkit-scrollbar-thumb:hover {
                background: var(--bamboo);
            }
            html, body {
                height: 100%;
                margin: 0;
                padding: 0;
                overflow: hidden;
            }
            body {
                -webkit-font-smoothing: antialiased;
            }
            
            /* Region drag control */
            header { -webkit-app-region: drag; }
            button, input, a, .no-drag { -webkit-app-region: no-drag; }

            /* Pacman Animation (Shadcn Green version) */
            .pacman-container {
                position: relative;
                width: 140px;
                height: 50px;
                display: flex;
                align-items: center;
                justify-content: flex-start;
            }
            .pacman {
                position: relative;
                z-index: 2;
                width: 40px;
                height: 40px;
                background: var(--bamboo);
                border-radius: 50%;
                clip-path: polygon(100% 0%, 100% 100%, 0% 100%, 0% 0%);
                animation: eat 0.3s infinite ease-in-out alternate;
            }
            .pacman::after {
                content: '';
                position: absolute;
                top: 8px;
                left: 20px;
                width: 4px;
                height: 4px;
                background: #000;
                border-radius: 50%;
            }
            @keyframes eat {
                0% { clip-path: polygon(100% 50%, 100% 100%, 0% 100%, 0% 0%, 100% 0%); }
                100% { clip-path: polygon(50% 50%, 100% 100%, 0% 100%, 0% 0%, 100% 0%); }
            }
            .dot {
                width: 6px;
                height: 6px;
                background: hsl(240 3.7% 15.9%);
                border-radius: 50%;
                margin-left: 20px;
                animation: dots 0.8s infinite linear;
            }
            @keyframes dots {
                0% { transform: translateX(100px); opacity: 0; }
                50% { opacity: 1; }
                100% { transform: translateX(-20px); opacity: 0; }
            }

            /* Spinner Minimalist */
            .spinner {
                width: 16px;
                height: 16px;
                border: 2px solid hsl(240 3.7% 15.9%);
                border-radius: 50%;
                border-top-color: var(--bamboo);
                animation: spin 0.8s linear infinite;
            }
            @keyframes spin {
                to { transform: rotate(360deg); }
            }

            /* Progress Bar Minimalist */
            .progress-bar-loading {
                position: absolute;
                bottom: 0;
                left: 0;
                height: 2px;
                background: var(--bamboo);
                animation: progress-load 2s infinite ease-in-out;
            }
            @keyframes progress-load {
                0% { width: 0; left: 0; }
                50% { width: 100%; left: 0; }
                100% { width: 0; left: 100%; }
            }
        </style>
    </head>
    <body class="antialiased bg-background text-foreground min-h-screen overflow-hidden">
        <livewire:store />
    </body>
</html>
