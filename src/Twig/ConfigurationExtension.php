<?php

namespace App\Twig;

use App\Entity\Configuration;
use App\Repository\ConfigurationRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose la configuration globale du site dans tous les templates via
 * configuration().couleurPrincipale, configuration().ficheClientActivee, etc.
 */
class ConfigurationExtension extends AbstractExtension
{
    public function __construct(
        private readonly ConfigurationRepository $configurationRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('configuration', [$this, 'getConfiguration']),
        ];
    }

    public function getConfiguration(): Configuration
    {
        return $this->configurationRepository->getOuCreer();
    }
}
