<?php
require_once 'identificadores.php';

class Aeronave
{
    private SiglaCompanhiaAerea $companhia_aerea;
    private string $fabricante;
    private string $modelo;
    private int $capacidade_passageiros;
    private float $capacidade_carga;
    private RegistroDeAeronave $registro;

    public function __construct(
        SiglaCompanhiaAerea $companhia_aerea,
        string              $fabricante,
        string              $modelo,
        int                 $capacidade_passageiros,
        float               $capacidade_carga,
        RegistroDeAeronave  $registro
    )
    {
        $this->companhia_aerea = $companhia_aerea;
        $this->fabricante = $fabricante;
        $this->modelo = $modelo;
        $this->capacidade_passageiros = $capacidade_passageiros;
        $this->capacidade_carga = $capacidade_carga;
        $this->registro = $registro;
    }

    public function getSigla(): SiglaCompanhiaAerea
    {
        return $this->companhia_aerea;
    }

    public function getFabricante(): string
    {
        return $this->fabricante;
    }

    public function getModelo(): string
    {
        return $this->modelo;
    }

    public function getCapacidadePassageiros(): int
    {
        return $this->capacidade_passageiros;
    }

    public function getCapacidadeCarga(): float
    {
        return $this->capacidade_carga;
    }

    public function getRegistro(): RegistroDeAeronave
    {
        return $this->registro;
    }
}
