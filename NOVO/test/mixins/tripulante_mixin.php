<?php
include_once "../../classes/tripulante.php";
include_once "../../classes/companhia_aerea.php";
include_once "endereco_mixin.php";
trait TripulanteMixin {
    use EnderecoMixin;
    use PassageiroMixin;

    protected function initTripulante() {
        $this->initEndereco();
    }
    private int $numeroCht = 0;
    protected function registrarTripulante(CompanhiaAerea &$companhiaAerea, Cargo $cargo, SiglaAeroporto $aeroportoBase = new SiglaAeroporto("GRU")): Tripulante
    {
        $passageiro = $this->passageiro();
        $cht = "CHT".$this->numeroCht;
        $this->numeroCht++;
        return $companhiaAerea->registrarTripulante(
            $passageiro->getNome(),
            $passageiro->getSobrenome(),
            $passageiro->getDocumento(),
            $passageiro->getNacionalidade(),
            $passageiro->getCpf(),
            $passageiro->getDataDeNascimento(),
            $passageiro->getEmail(),
            $cht,
            $this->endereco,
            $aeroportoBase,
            $cargo
        );
    }
}