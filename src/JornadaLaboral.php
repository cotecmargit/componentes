<?php

namespace Cotecmar\Servicio;

use Carbon\Carbon;

class JornadaLaboral
{
    public static $tipos = [
        'ORD' => ['tipoDia' => 'ORD', 'inicio' => '06:00', 'fin' => '21:00', 'porcentaje' => 1],
        'RN' => ['tipoDia' => 'ORD', 'inicio' => '21:00', 'fin' => '06:00', 'porcentaje' => 1.35],
        'HED' => ['tipoDia' => 'ORD', 'inicio' => '06:00', 'fin' => '21:00', 'porcentaje' => 1.25],
        'HEN' => ['tipoDia' => 'ORD', 'inicio' => '21:00', 'fin' => '06:00', 'porcentaje' => 1.75],
        'DF' => ['tipoDia' => 'DF', 'inicio' => '06:00', 'fin' => '21:00', 'porcentaje' => 1.80],
        'DFRN' => ['tipoDia' => 'DF', 'inicio' => '21:00', 'fin' => '06:00', 'porcentaje' => 2.1],
        'DFHED' => ['tipoDia' => 'DF', 'inicio' => '06:00', 'fin' => '21:00', 'porcentaje' => 2],
        'DFHEN' => ['tipoDia' => 'DF', 'inicio' => '21:00', 'fin' => '06:00', 'porcentaje' => 2.5],
    ];

    public static function obtenerSegmentos($fecha, $horaEntrada, $horaSalida, $calendario)
    {
        // Ejemplo de uso del calendario con franjas horarias recurrentes de trabajo y descanso
        // $calendario = [
        //     [
        //         'recurrentStartDate' => "on Mon,Tue,Wed,Thu,Fri at 07:00",
        //         'recurrentEndDate' => "on Mon,Tue,Wed,Thu,Fri at 15:00",
        //     ],
        //     // [
        //     //     'recurrentStartDate' => "on Mon,Tue,Wed,Thu,Fri at 13:00",
        //     //     'recurrentEndDate' => "on Mon,Tue,Wed,Thu,Fri at 16:30",
        //     // ]
        // ];

        // Determina si el día es festivo o domingo para clasificar el tipo de día (DF para festivo, ORD para ordinario)
        $tipoDia = self::esFestivoODomingo($fecha) ? 'DF' : 'ORD';
        $resultados = [];

        // Convierte las horas de entrada y salida en objetos Carbon para facilitar cálculos de tiempo
        $entrada = Carbon::parse("$fecha $horaEntrada");
        $salida = Carbon::parse("$fecha $horaSalida");

        // Si la hora de salida es menor o igual a la entrada, se asume que la jornada termina al día siguiente
        if ($salida <= $entrada) {
            $salida->addDay();
        }

        // Obtiene los horarios de la empresa para la fecha dada según el calendario
        $horasEmpresa = self::obtenerHorasPorDia($calendario, $fecha);
        // Ejemplo resultado: ['07:00', '12:00', '13:00', '16:30']
        if (is_array($horasEmpresa) || count($horasEmpresa) > 0) {
            $horaEntradaEmpresa = $horasEmpresa[0];
            $horaSalidaEmpresa = $horasEmpresa[1];
            $horaEntradaDescansoEmpresa = $horasEmpresa[2];
            $horaSalidaDescansoEmpresa = $horasEmpresa[3];
        }
        // dd('', $horaEntradaEmpresa, '', $horaSalidaEmpresa,'', $horaEntradaDescansoEmpresa, '', $horaSalidaDescansoEmpresa);
        // Define los límites del horario ordinario de la empresa en objetos Carbon
        if ($horaEntradaEmpresa && $horaSalidaEmpresa) {
            $inicioOrdinario = Carbon::parse("$fecha $horaEntradaEmpresa");
            $finOrdinario = Carbon::parse("$fecha $horaSalidaEmpresa");
            if ($finOrdinario <= $inicioOrdinario) {
                $finOrdinario->addDay();
            }
        } else {
            // Si no hay horario definido, se inicializan como null
            $inicioOrdinario = null;
            $finOrdinario = null;
        }

        // Si hay horarios de descanso, se definen sus límites en objetos Carbon y se ajustan fechas si cruzan medianoche
        if ($horaEntradaDescansoEmpresa && $horaSalidaDescansoEmpresa) {
            $inicioOrdinarioDescanso = Carbon::parse("$fecha $horaEntradaDescansoEmpresa");
            $finOrdinarioDescanso = Carbon::parse("$fecha $horaSalidaDescansoEmpresa");
            if ($inicioOrdinarioDescanso < $inicioOrdinario) {
                $inicioOrdinarioDescanso->addDay();
                $finOrdinarioDescanso->addDay();
            }
            if ($finOrdinarioDescanso->lessThanOrEqualTo($inicioOrdinarioDescanso)) {
                $finOrdinarioDescanso->addDay();
            }
            // Añade 1 minuto al inicio del descanso para no contar ese instante en la jornada ordinaria
            $inicioOrdinarioDescanso->addMinutes(1);
        } else {
            // Si no hay descanso, se inicializan como null
            $inicioOrdinarioDescanso = null;
            $finOrdinarioDescanso = null;
        }

        // Calcula la diferencia en minutos entre inicio y fin de la jornada ordinaria de la empresa (no se usa después)
        if ($inicioOrdinario && $finOrdinario) {
            $diferenciaMinutos = $inicioOrdinario->diffInMinutes($finOrdinario);
        } else {
            $diferenciaMinutos = 0;
        }
        // dd($diferenciaMinutos);
        // Calcula la diferencia en minutos entre la entrada y salida del empleado
        $diferenciaMinutos = $entrada->diffInMinutes($salida);
        // Convierte minutos a horas decimales redondeando a dos decimales
        $totalHoras = round($diferenciaMinutos / 60, 2);

        // Clona la hora de entrada para comenzar el recorrido de segmentos
        $actual = $entrada->copy();
        $horasOrdinariasAcum = 0;

        // dd("Inicio cálculo segmentos desde {$actual} hasta {$salida} (total horas: {$totalHoras})");

        // Mientras la hora actual sea menor que la hora de salida, se avanza en segmentos de 30 minutos
        while ($actual < $salida) {
            $segmentoFin = $actual->copy()->addMinutes(30); // Avanza media hora
            if ($segmentoFin > $salida) {
                $segmentoFin = $salida->copy(); // Ajusta último segmento si pasa de salida
            }
            // Calcula duración del segmento en horas (fracción decimal)
            $horasSegmento = $actual->diffInMinutes($segmentoFin) / 60;

            // Si hay descanso y el segmento está dentro de éste, se salta sin acumular horas
            if ($inicioOrdinarioDescanso && $finOrdinarioDescanso) {
                if ($segmentoFin->between($inicioOrdinarioDescanso, $finOrdinarioDescanso, true)) {
                    $actual = $segmentoFin;
                    // Actualiza tipo de día según fecha actual del segmento
                    $tipoDia = self::esFestivoODomingo($segmentoFin->toDateString()) ? 'DF' : 'ORD';
                    continue; // Continúa al siguiente segmento sin acumular horas
                }
            }

            // Verifica si el segmento cae en horario nocturno según reglas de la empresa
            if ($inicioOrdinario)
                $esNocturno = self::esHorarioNocturno($inicioOrdinario, $segmentoFin);
            else
                $esNocturno = self::esHorarioNocturno($entrada, $segmentoFin);

            // Log::info("Segmento desde {$actual} hasta {$segmentoFin} ({$horasSegmento} hrs) - Tipo Día: {$tipoDia} - Nocturno: " . ($esNocturno ? 'Sí' : 'No'));

            // Verifica si el segmento está dentro del horario ordinario de empresa
            if ($inicioOrdinario && $finOrdinario) {
                $finOrdinarioOrigen = $finOrdinario->copy();
                if ($tipoDia === 'DF') {
                    // Calcular diferencia en minutos entre las dos fechas
                    $diferenciaMinutos = $inicioOrdinario->diffInMinutes($finOrdinario);
                    // Convertir minutos a horas decimales
                    $diferenciaHoras = round($diferenciaMinutos / 60, 2);
                    if ($inicioOrdinarioDescanso && $finOrdinarioDescanso) {
                        // Calcular diferencia en minutos entre inicio y fin del descanso
                        $diferenciaMinutosDescanso = $inicioOrdinarioDescanso->diffInMinutes($finOrdinarioDescanso);

                        // Convertir minutos a horas decimales redondeadas a dos decimales
                        $diferenciaHorasDescanso = round($diferenciaMinutosDescanso / 60, 2);
                        $diferenciaHoras -= round($diferenciaHorasDescanso * 2) / 2;
                        // Aquí puede usar $diferenciaHorasDescanso para ajustar horarios o cálculos posteriores
                    }
                    // dd($finOrdinario);
                    if ($diferenciaHoras > 8) {
                        // Calcular cuántas horas exceden de 8
                        $horasExcedentes = $diferenciaHoras - 8;
                        $minutosArestar = $horasExcedentes * 60;
                        // Restar esas horas excedentes a $finOrdinario usando Carbon
                        $finOrdinarioOrigen = $finOrdinario->copy()->subMinutes($minutosArestar);
                    }
                }
                // Log::info(" - Inicio ordinario origen ajustado: $inicioOrdinario - Fin ordinario origen ajustado: $finOrdinarioOrigen -- Segmento fin: $segmentoFin");
                $empleadoEnHorarioEmpresa = ($inicioOrdinario <= $segmentoFin) && ($finOrdinarioOrigen >= $segmentoFin);

            } else
                $empleadoEnHorarioEmpresa = false;

            // Log::info(" - En horario empresa: " . ($empleadoEnHorarioEmpresa ? 'Sí' : 'No'));

            // Calcula cuántas horas quedan por acumular para no superar total
            $horasRestantes = max(0, $totalHoras - $horasOrdinariasAcum);
            $horasASumar = min($horasSegmento, $horasRestantes);
            $horasOrdinariasAcum += $horasASumar;

            // Acumula horas según clasificación del segmento y tipo de día
            if ($empleadoEnHorarioEmpresa) {
                if (!$esNocturno) {
                    if ($tipoDia === 'ORD') {
                        $resultados['ORD'] = ($resultados['ORD'] ?? 0) + $horasASumar;
                    } elseif ($tipoDia === 'DF') {
                        $resultados['DF'] = ($resultados['DF'] ?? 0) + $horasASumar;
                    }
                } else {
                    if ($tipoDia === 'ORD') {
                        $resultados['RN'] = ($resultados['RN'] ?? 0) + $horasASumar;
                    } elseif ($tipoDia === 'DF') {
                        $resultados['DFRN'] = ($resultados['DFRN'] ?? 0) + $horasASumar;
                    }
                }
            } else {
                // Fuera del horario ordinario se consideran horas extras, diurnas o nocturnas y día festivo o no
                if ($esNocturno) {
                    if ($tipoDia === 'ORD') {
                        $resultados['HEN'] = ($resultados['HEN'] ?? 0) + $horasASumar;
                    } elseif ($tipoDia === 'DF') {
                        $resultados['DFHEN'] = ($resultados['DFHEN'] ?? 0) + $horasASumar;
                    }
                } else {
                    if ($tipoDia === 'ORD') {
                        $resultados['HED'] = ($resultados['HED'] ?? 0) + $horasASumar;
                    } elseif ($tipoDia === 'DF') {
                        $resultados['DFHED'] = ($resultados['DFHED'] ?? 0) + $horasASumar;
                    }
                }
            }

            // Avanza al siguiente segmento
            $actual = $segmentoFin;
            // Actualiza el tipo de día para el siguiente segmento
            $tipoDia = self::esFestivoODomingo($segmentoFin->toDateString()) ? 'DF' : 'ORD';
        }
        // Redondea las horas acumuladas en cada categoría a un decimal
        foreach ($resultados as $key => $value) {
            $resultados[$key] = round($value, 1);
        }
        // Retorna el array con el total de horas acumuladas por categoría
        return $resultados;
    }

    public static function calcularCostoTotal($fecha, $horaEntrada, $horaSalida, $calendario, $salarioMensualCosto, $subtransporte = 0)
    {
        $horasPorTipo = self::obtenerSegmentos($fecha, $horaEntrada, $horaSalida, $calendario);
        // $salarioMensualCosto = $salarioMensual * 1.66;

        $total = 0;
        foreach ($horasPorTipo as $tipo => $horas) {
            if (isset(self::$tipos[$tipo])) {
                $tipoInfo = self::$tipos[$tipo];
                if ($tipo === 'ORD' || $tipo === 'RN')
                    $valorBaseHoraMes = ($salarioMensualCosto + $subtransporte) / 170; // Valor hora base costo
                else
                    $valorBaseHoraMes = $salarioMensualCosto / 240; // Valor hora base mensual

                $total += $valorBaseHoraMes * $tipoInfo['porcentaje'] * $horas;
            }
        }
        return $total;
    }
    public static function esFestivoODomingo($fecha)
    {
        return Carbon::parse($fecha)->isSunday() || self::esFeriado($fecha);
    }
    public static function esHorarioNocturno(Carbon $fecha, Carbon $hora)
    {
        // Define inicio y fin de horario nocturno
        $inicioNocturno = Carbon::parse($fecha->format('Y-m-d') . ' 21:00:01');
        $finNocturno = Carbon::parse($fecha->format('Y-m-d') . ' 06:00:00')->addDay();
        // Log::info("Verificando horario nocturno para {$hora}: entre {$inicioNocturno} y {$finNocturno}");
        // Si la hora es después de las 22:00 o antes de las 06:00 siguiente,
        // se considera horario nocturno
        return $hora->between($inicioNocturno, $finNocturno->copy());
    }

    public static function obtenerHorasPorDia($calendars, $fecha)
    {
        if (!is_array($calendars) || empty($calendars)) {
            return [null, null, null, null];
        }

        $shortDay = date('D', strtotime($fecha));
        $horas = [];

        foreach ($calendars as $regla) {
            if (is_array($regla)) {
                // Procesa cada columna de cadena separadamente
                foreach (['recurrentStartDate', 'recurrentEndDate'] as $campo) {
                    if (isset($regla[$campo]) && is_string($regla[$campo])) {
                        if (preg_match_all('/on ([^ ]+) at ([0-9]{2}:[0-9]{2})/', $regla[$campo], $matches, PREG_SET_ORDER)) {
                            foreach ($matches as $match) {
                                $dias = explode(',', $match[1]);
                                $hora = $match[2];
                                if (in_array($shortDay, $dias)) {
                                    $horas[] = $hora;
                                }
                            }
                        }
                    }
                }
            }
        }
        // dd($horas);
        $horaEntradaEmpresa = $horas[0] ?? null;
        $horaSalidaEmpresa = !empty($horas) ? end($horas) : null;

        if (count($horas) > 2) {
            $horaEntradaDescansoEmpresa = $horas[1];
            $horaSalidaDescansoEmpresa = $horas[count($horas) - 2];
        } else {
            $horaEntradaDescansoEmpresa = null;
            $horaSalidaDescansoEmpresa = null;
        }

        return [
            $horaEntradaEmpresa,
            $horaSalidaEmpresa,
            $horaEntradaDescansoEmpresa,
            $horaSalidaDescansoEmpresa,
        ];
    }

    public static function esFeriado($fecha)
    {
        $anio = date('Y', strtotime($fecha));
        $fechaFormateada = date('Y-m-d', strtotime($fecha));

        // Feriados fijos sin traslado
        $feriadosFijos = [
            "$anio-01-01", // Año Nuevo
            "$anio-05-01", // Día del Trabajo
            "$anio-07-20", // Independencia de Colombia
            "$anio-08-07", // Batalla de Boyacá
            "$anio-12-08", // Inmaculada Concepción
            "$anio-12-25"  // Navidad
        ];

        // Feriados con posible traslado a lunes (Ley Emiliani)
        $feriadosMoviles = [
            '01-06', // Reyes Magos
            '03-19', // San José
            '06-29', // San Pedro y San Pablo
            '08-15', // Asunción de la Virgen
            '10-12', // Día de la Raza
            '11-01', // Todos los Santos
            '11-11'  // Independencia de Cartagena
        ];

        // Calcular feriados con traslado a lunes
        $feriadosTrasladados = [];
        foreach ($feriadosMoviles as $diaMes) {
            $fechaOriginal = strtotime("$anio-$diaMes");
            $diaSemana = date('N', $fechaOriginal); // 1=Lunes .. 7=Domingo
            if ($diaSemana != 1) {
                // Se traslada al siguiente lunes
                $feriadosTrasladados[] = date('Y-m-d', strtotime('next monday', $fechaOriginal));
            } else {
                $feriadosTrasladados[] = date('Y-m-d', $fechaOriginal);
            }
        }

        // Calcular feriados basados en Pascua
        $domingoPascua = self::calcularDomingoPascua($anio);
        $juevesSanto = date('Y-m-d', strtotime("$domingoPascua -3 days"));
        $viernesSanto = date('Y-m-d', strtotime("$domingoPascua -2 days"));
        $ascension = date('Y-m-d', strtotime("$domingoPascua +39 days"));
        $corpusChristi = date('Y-m-d', strtotime("$domingoPascua +60 days"));
        $sagradoCorazon = date('Y-m-d', strtotime("$domingoPascua +68 days"));

        // Aplicar traslado a lunes a los feriados basados en Pascua
        $feriadosPascua = [];
        foreach ([$ascension, $corpusChristi, $sagradoCorazon] as $fechaPascua) {
            $diaSemana = date('N', strtotime($fechaPascua));
            if ($diaSemana != 1) {
                $feriadosPascua[] = date('Y-m-d', strtotime('next monday', strtotime($fechaPascua)));
            } else {
                $feriadosPascua[] = $fechaPascua;
            }
        }
        // Agregar jueves y viernes santo sin traslado
        $feriadosPascua[] = $juevesSanto;
        $feriadosPascua[] = $viernesSanto;

        // Unir todos los feriados
        $todosLosFeriados = array_merge($feriadosFijos, $feriadosTrasladados, $feriadosPascua);

        // Verificar si la fecha está en la lista de feriados
        return in_array($fechaFormateada, $todosLosFeriados);
    }

    private static function calcularDomingoPascua($anio)
    {
        // Algoritmo de Gauss para la fecha de Pascua
        $a = $anio % 19;
        $b = intdiv($anio, 100);
        $c = $anio % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;
        return date('Y-m-d', strtotime("$anio-$mes-$dia"));
    }

    // $resultados = JornadaLaboralHelper::obtenerSegmentos('2025-10-22', '20:00', '04:00', $calendario);

}
