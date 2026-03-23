<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * Noyau Symfony de l'application front Twig.
 */
class Kernel extends BaseKernel
{
    // Le MicroKernelTrait permet à Symfony de charger la configuration standard du projet.
    use MicroKernelTrait;
}
