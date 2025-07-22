<?php

namespace Cotecmar\Servicio;

class ObjectGeneral
{
    public $id;
    public $name;
    public $descripcion;

    function __construct($id, $name, $descripcion)
    {
        $this->id = $id;
        $this->name = $name;
        $this->descripcion = $descripcion;
    }
}
