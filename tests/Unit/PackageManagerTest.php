<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Enums\PackageSource;
use App\Services\PackageManagers\PackageManagerFactory;
use App\Services\PackageManagers\PacmanManager;
use App\Services\PackageManagers\AurManager;
use App\Services\PackageManagers\FlatpakManager;

class PackageManagerTest extends TestCase
{
    /**
     * Test that PackageManagerFactory returns the correct implementation.
     */
    public function test_factory_resolves_correct_managers(): void
    {
        $pacman  = PackageManagerFactory::make(PackageSource::PACMAN);
        $this->assertInstanceOf(PacmanManager::class, $pacman);

        $aur     = PackageManagerFactory::make(PackageSource::AUR);
        $this->assertInstanceOf(AurManager::class, $aur);

        $flatpak = PackageManagerFactory::make(PackageSource::FLATPAK);
        $this->assertInstanceOf(FlatpakManager::class, $flatpak);
    }

    /**
     * Test command generation for Pacman operations.
     */
    public function test_pacman_manager_generates_correct_commands(): void
    {
        $manager = $this->getMockBuilder(PacmanManager::class)
            ->onlyMethods(['spawnTerminal'])
            ->getMock();

        $manager->expects($this->once())
            ->method('spawnTerminal')
            ->with(
                $this->equalTo("sudo pacman -S --noconfirm 'test-package'"),
                $this->stringContains('test-package')
            )
            ->willReturn(9999);

        $result = $manager->install('test-package');
        $this->assertEquals(9999, $result);

        // Test removal command
        $manager2 = $this->getMockBuilder(PacmanManager::class)
            ->onlyMethods(['spawnTerminal'])
            ->getMock();

        $manager2->expects($this->once())
            ->method('spawnTerminal')
            ->with(
                $this->equalTo("sudo pacman -Rns --noconfirm 'test-package'"),
                $this->stringContains('test-package')
            )
            ->willReturn(9999);

        $result2 = $manager2->remove('test-package');
        $this->assertEquals(9999, $result2);
    }

    /**
     * Test command generation for AUR operations.
     */
    public function test_aur_manager_generates_correct_commands(): void
    {
        $manager = $this->getMockBuilder(AurManager::class)
            ->onlyMethods(['spawnTerminal', 'getHelper'])
            ->getMock();

        // Force helper to be 'yay' for test stability
        $manager->method('getHelper')->willReturn('yay');

        $manager->expects($this->once())
            ->method('spawnTerminal')
            ->with(
                $this->equalTo("yay -S 'test-package'"),
                $this->stringContains('test-package')
            )
            ->willReturn(8888);

        $result = $manager->install('test-package');
        $this->assertEquals(8888, $result);
    }

    /**
     * Test command generation for Flatpak operations.
     */
    public function test_flatpak_manager_generates_correct_commands(): void
    {
        $manager = $this->getMockBuilder(FlatpakManager::class)
            ->onlyMethods(['spawnTerminal'])
            ->getMock();

        $manager->expects($this->once())
            ->method('spawnTerminal')
            ->with(
                $this->equalTo("flatpak install -y flathub 'test-package'"),
                $this->stringContains('test-package')
            )
            ->willReturn(7777);

        $result = $manager->install('test-package');
        $this->assertEquals(7777, $result);

        // Test removal command
        $manager2 = $this->getMockBuilder(FlatpakManager::class)
            ->onlyMethods(['spawnTerminal'])
            ->getMock();

        $manager2->expects($this->once())
            ->method('spawnTerminal')
            ->with(
                $this->equalTo("flatpak uninstall -y 'test-package'"),
                $this->stringContains('test-package')
            )
            ->willReturn(7777);

        $result2 = $manager2->remove('test-package');
        $this->assertEquals(7777, $result2);
    }
}
