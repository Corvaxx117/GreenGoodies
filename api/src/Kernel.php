<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * Noyau Symfony de l'application API.
 */
class Kernel extends BaseKernel
{
    // Le MicroKernelTrait permet le chargement standard de la configuration Symfony du projet.
    use MicroKernelTrait;
}
