<?php

namespace Cotecmar\Servicio;

use Carbon\Carbon;

class JornadaLaboralHelper
{
    public static $tipos = [
        'ORD'   => ['tipoDia' => 'ORD', 'inicio' => '06:00', 'fin' => '21:00', 'extra' => false],
        'RN'    => ['tipoDia' => 'ORD', 'inicio' => '21:00', 'fin' => '06:00', 'extra' => false],
        'HED'   => ['tipoDia' => 'ORD', 'inicio' => '06:00', 'fin' => '21:00', 'extra' => true],
        'HEN'   => ['tipoDia' => 'ORD', 'inicio' => '21:00', 'fin' => '06:00', 'extra' => true],
        'DF'    => ['tipoDia' => 'DF',  'inicio' => '06:00', 'fin' => '21:00', 'extra' => false],
        'DFRN'  => ['tipoDia' => 'DF',  'inicio' => '21:00', 'fin' => '06:00', 'extra' => false],
        'DFHED' => ['tipoDia' => 'DF',  'inicio' => '06:00', 'fin' => '21:00', 'extra' => true],
        'DFHEN' => ['tipoDia' => 'DF',  'inicio' => '21:00', 'fin' => '06:00', 'extra' => true],
    ];

    public static function obtenerSegmentos($fecha, $horaEntrada, $horaSalida, $diasFestivos = [])
    {
        // Determina si el día inicial es festivo o dominical
        $tipoDia = self::esFestivoODomingo($fecha, $diasFestivos) ? 'DF' : 'ORD';

        $resultados = [];
        $entrada = Carbon::parse("$fecha $horaEntrada");
        $salida = Carbon::parse("$fecha $horaSalida");

        // Si la salida es anterior a la entrada, ajusta para el día siguiente
        if ($salida <= $entrada) {
            $salida->addDay();
        }

        $actual = $entrada->copy();

        // Variables para controlar horas ordinarias acumuladas
        $horasOrdinariasAcum = 0;

        while ($actual < $salida) {
            foreach (self::$tipos as $codigo => $info) {
                // Si el código es ORD o DF para el día correspondiente o recargo nocturno RN o DFRN (que pueden cruzar días)
                $esRecargoNocturno = in_array($codigo, ['RN', 'DFRN']);
                $esCodigoDiaActual = $info['tipoDia'] === $tipoDia;

                if (($esCodigoDiaActual && in_array($codigo, ['ORD', 'DF'])) || $esRecargoNocturno || in_array($codigo, ['HED', 'HEN', 'DFHED', 'DFHEN'])) {
                    if (self::enHorario($actual, $info['inicio'], $info['fin'])) {
                        $nextCambio = self::siguienteCambio($actual, $info['inicio'], $info['fin']);
                        $segmentoFin = $nextCambio < $salida ? $nextCambio : $salida;
                        $horasSegmento = $actual->diffInMinutes($segmentoFin) / 60;

                        // Para ORD y DF limitamos a máximo 8 horas ordinarias totales
                        if (in_array($codigo, ['ORD', 'DF'])) {
                            $horasRestantes = max(0, 8 - $horasOrdinariasAcum);
                            $horasASumar = min($horasSegmento, $horasRestantes);
                            if ($horasASumar <= 0) {
                                // Ya se completaron horas ordinarias, se pasa al siguiente código (extras)
                                continue;
                            }
                            $horasOrdinariasAcum += $horasASumar;
                            $resultados[$codigo] = ($resultados[$codigo] ?? 0) + $horasASumar;

                            // Si quedaron horas no asignadas porque se pasó el límite 8, esas serán horas extras
                            $horasExtras = $horasSegmento - $horasASumar;
                            if ($horasExtras > 0) {
                                // Clasificar extras según si es diurno o nocturno y si es día festivo o no
                                $codigoExtra = self::determinarCodigoExtra($actual, $tipoDia);
                                $resultados[$codigoExtra] = ($resultados[$codigoExtra] ?? 0) + $horasExtras;
                            }
                        } else {
                            // Recargos y extras suman normalmente
                            $resultados[$codigo] = ($resultados[$codigo] ?? 0) + $horasSegmento;
                        }

                        $actual = $segmentoFin;
                        break; // romper foreach para avanzar en tiempo
                    }
                }
            }
        }

        return $resultados;
    }

    /**
     * Función para determinar código extra basado en hora y tipo de día.
     * Ejemplo simple que diferencia horas extras diurnas/nocturnas y festivas.
     */
    protected static function determinarCodigoExtra(Carbon $fechaHora, string $tipoDia): string
    {
        $hora = $fechaHora->format('H:i');
        $esNocturno = $hora >= '21:00' && $hora < '06:00';

        if ($tipoDia === 'DF') {
            return $esNocturno ? 'DFHEN' : 'DFHED';
        } else {
            return $esNocturno ? 'HEN' : 'HED';
        }
    }

    public static function esFestivoODomingo($fecha, $diasFestivos)
    {
        return Carbon::parse($fecha)->isSunday() || in_array($fecha, $diasFestivos);
    }
    public static function enHorario($actual, $inicio, $fin)
    {
        $hora = $actual->format('H:i');
        if ($inicio < $fin) {
            return $hora >= $inicio && $hora < $fin;
        } else {
            return $hora >= $inicio || $hora < $fin;
        }
    }
    public static function siguienteCambio($actual, $inicio, $fin)
    {
        $fecha = $actual->format('Y-m-d');
        $horaActual = $actual->format('H:i');
        $horaInicio = Carbon::parse("$fecha $inicio");
        $horaFin = Carbon::parse("$fecha $fin");
        if ($horaActual < $inicio) return $horaInicio;
        if ($inicio < $fin) return $horaFin;
        return $horaFin->addDay();
    }

    // $diasFestivosArray = ['2025-10-22', '2025-10-23'] // siempres y cuando sean feriados si no va vacio;
    // $resultados = JornadaLaboralHelper::obtenerSegmentos('2025-10-22', '20:00', '04:00', $diasFestivosArray);

}
