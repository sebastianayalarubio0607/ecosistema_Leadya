<?php

use App\Jobs\RefreshMetaLongLivedTokenJob;
use App\Jobs\SyncMetaFormsJob;
use App\Jobs\SyncMetaLeadsJob;
use App\Jobs\SyncMetaPagesJob;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/**
 * este archivo define los comandos de consola personalizados y las tareas programadas para la aplicación.
 * Se utiliza para registrar comandos de Artisan personalizados que pueden ser ejecutados desde la línea de comandos, así como para programar tareas que se ejecutarán automáticamente en momentos específicos o a intervalos regulares.
 * En este caso, se define un comando 'inspire' que muestra una cita inspiradora, y se programan varias tareas relacionadas con la sincronización de datos de Meta (Facebook), como la sincronización de insights, páginas, formularios y leads, así como el refresco
 */
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


/**
 * Aquí se definen las tareas programadas para la aplicación utilizando el scheduler de Laravel. Estas tareas se ejecutarán automáticamente según la programación definida, lo que permite mantener los datos sincronizados y realizar tareas de mantenimiento sin intervención manual.
 * Se programan tareas para sincronizar insights de Meta, refrescar tokens de larga duración, sincronizar páginas, formularios y leads de Meta Lead Ads. Cada tarea se configura para ejecutarse en momentos específicos (por ejemplo, diariamente o cada hora) y se asigna a colas específicas para organizar y priorizar las tareas en el sistema de colas.
 * Además, se utiliza el middleware WithoutOverlapping para evitar que se ejecuten múltiples instancias de la misma tarea al mismo tiempo, lo que ayuda a prevenir conflictos o sobrecarga en el sistema. En caso de que una tarea falle, el sistema de colas manejará los reintentos según la configuración establecida en
 * cada trabajo, y se pueden implementar métodos de manejo de fallos para registrar errores o notificar a los administradores, asegurando que los problemas sean visibles y puedan ser atendidos.
 * Se recomienda revisar los logs de la aplicación para monitorear la ejecución de estas tareas programadas y asegurarse de que se estén ejecutando correctamente, así como para identificar y resolver cualquier problema que pueda surgir durante su ejecución.
 * Es importante también asegurarse de que el sistema de colas esté configurado y funcionando correctamente
 */

// Sincroniza insights de Meta Lead Ads diariamente a las 2:00 AM hora de Bogotá
Schedule::command('meta:sync-insights-yesterday --timezone=America/Bogota')
    ->dailyAt('02:00')
    ->timezone('America/Bogota');

    // Refresca los tokens de larga duración de Meta diariamente a las 3:00 AM hora de Bogotá
Schedule::job(new RefreshMetaLongLivedTokenJob())
    ->hourly()
    ->timezone('America/Bogota')
    ->withoutOverlapping();

// Sincroniza las páginas de Meta Lead Ads cada hora
Schedule::job(new SyncMetaPagesJob())
    ->hourly()
    ->timezone('America/Bogota')
    ->withoutOverlapping();

    // Sincroniza los formularios de Meta Lead Ads cada hora
Schedule::job(new SyncMetaFormsJob())
    ->hourly()
    ->timezone('America/Bogota')
    ->withoutOverlapping();

    // Sincroniza los leads de Meta Lead Ads cada hora  
Schedule::job(new SyncMetaLeadsJob())
    ->hourly()
    ->timezone('America/Bogota')
    ->withoutOverlapping();
