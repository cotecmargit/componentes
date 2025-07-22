<?php

namespace Cotecmar\Servicio;

use Illuminate\Support\Str;
use Carbon\Carbon;

class Main
{

    public static function gerencias()
    {
        return [
            new ObjectGeneral(1, 'PCTMAR', 'Presidencia'),
            new ObjectGeneral(2, 'VPEXE', 'Vicepresidencia Ejecutiva'),
            new ObjectGeneral(3, 'VPT&O', 'Vicepresidencia Tecnologia y Operaciones'),
            new ObjectGeneral(4, 'GEFAD', 'Gerencia Financiera y Administrativa'),
            new ObjectGeneral(5, 'GETHU', 'Gerencia de Talento Humano'),
            new ObjectGeneral(6, 'GEMAM', 'Gerencia Mamonal'),
            new ObjectGeneral(7, 'GEBOC', 'Gerencia Bocagrande'),
            new ObjectGeneral(8, 'GECON', 'Gerencia Contrucciones'),
            new ObjectGeneral(9, 'GEDIN', 'Gerencia Diseño e Ingenieria'),
            new ObjectGeneral(10, 'GECTI', 'Gerencia Ciencia Tecnologia e Innovación')
        ];
    }

     public static function gruposConstructivos()
    {
        return [
            new ObjectGeneral(0, '000', 'GENERALIDADES Y ADMINISTRACIÓN'),
            new ObjectGeneral(1, '100', 'ESTRUCTURA DEL CASCO'),
            new ObjectGeneral(2, '200', 'PLANTA PROPULSORA'),
            new ObjectGeneral(3, '300', 'PLANTA ELÉCTRICA'),
            new ObjectGeneral(4, '400', 'MANDO Y ESPLORACIÓN'),
            new ObjectGeneral(5, '500', 'SISTEMAS AUXILIARES'),
            new ObjectGeneral(6, '600', 'ACABADOS Y AMOBLAMIENTO'),
            new ObjectGeneral(7, '700', 'SISTEMA DE ARMAS'),
            new ObjectGeneral(8, '800', 'INTEGRACIÓN/INGENIERÍA'),
            new ObjectGeneral(9, '900', 'MONTAJE DEL BUQUE Y SERVICIOS DE APOYOS'),
         
        ];
    }


    public static function gerenciasSAP($gerenciaSAP)
    {
        $gerencia = [];
        if ($gerenciaSAP == 'FADM') //	GERENCIA FINANCIERA Y ADMINISTRATIVA	
            $gerencia = array_filter(Main::gerencias(), function ($gerencia) {
                return $gerencia->id == 4;
            });
        else if ($gerenciaSAP == 'VICE') //	VICEPRESIDENCIA EJECUTIVA	
            $gerencia = array_filter(Main::gerencias(), function ($gerencia) {
                return $gerencia->id == 2;
            });
        else if ($gerenciaSAP == 'DING') //	GERENCIA DISEÑO E INGENIERIA	
            $gerencia = array_filter(Main::gerencias(), function ($gerencia) {
                return $gerencia->id == 9;
            });
        else if ($gerenciaSAP == 'BGDE') //	GERENCIA PLANTA BOCAGRANDE	
            $gerencia = array_filter(Main::gerencias(), function ($gerencia) {
                return $gerencia->id == 7;
            });
        else if ($gerenciaSAP == 'MNAL') //	GERENCIA PLANTA MAMONAL	
            $gerencia = array_filter(Main::gerencias(), function ($gerencia) {
                return $gerencia->id == 6;
            });
        else if ($gerenciaSAP == 'VICO') //	VICEPRESIDENCIA TECNOLOGICA Y DE OPERACIONES	
            $gerencia = array_filter(Main::gerencias(), function ($gerencia) {
                return $gerencia->id == 3;
            });
        else if ($gerenciaSAP == 'PRES') //	PRESIDENCIA	
            $gerencia = array_filter(Main::gerencias(), function ($gerencia) {
                return $gerencia->id == 1;
            });
        else if ($gerenciaSAP == 'CTIN') //	GERENCIA DE CIENCIA, TECNOLOGIA E INNOVACION	
            $gerencia = array_filter(Main::gerencias(), function ($gerencia) {
                return $gerencia->id == 10;
            });
        else if ($gerenciaSAP == 'CONS') //	GERENCIA CONSTRUCCIONES	
            $gerencia = array_filter(Main::gerencias(), function ($gerencia) {
                return $gerencia->id == 8;
            });
        else if ($gerenciaSAP == 'THUM') //	GERENCIA TALENTO HUMANO	
            $gerencia = array_filter(Main::gerencias(), function ($gerencia) {
                return $gerencia->id == 5;
            });
        return reset($gerencia);
    }


    public static function contarFechasEnMes($fechainicio, $fechafinal)
    {
        return (($fechafinal->year - $fechainicio->year) * 12) + $fechafinal->month - $fechainicio->month;
    }

    public static function fechaConvertirSAP($fecha)
    {
        $año = substr($fecha, 0, 4);
        $mes = substr($fecha, 4, 2);
        $dia = substr($fecha, 6, 2);
        $fechaMin = Carbon::createFromDate($año, $mes, $dia);
        return $fechaMin;
    }

    public static function convertirFechaNumero($fecha)
    {
        $año = $fecha->year;
        $mes = self::rellenarCerrosIzquierda($fecha->month, 2);
        $dia = self::rellenarCerrosIzquierda($fecha->day, 2);
        return ($año . $mes . $dia);
    }

    public static function rellenarCerrosIzquierda($numero, $longitud)
    {
        if (Str::length($numero) < $longitud) {
            return self::rellenarEspaciosVariable("0", $longitud - Str::length($numero)) . $numero;
        }
        return substr($numero, 0, $longitud);
    }

    public static function rellenarEspaciosVariable($variable, $longitud)
    {
        $var = "";
        do {
            $var = $var . $variable;
        } while (Str::length($var) < $longitud);
        return substr($var, 0, $longitud);
    }

    public static function UnidadesNegocio(){
        return [
            new ObjectGeneral(1, 'BGD', 'Bocagrande'),
            new ObjectGeneral(1, 'MAM', 'Mamonal'),
        ];
    }
}
