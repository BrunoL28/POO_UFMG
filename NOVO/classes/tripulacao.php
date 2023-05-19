<?php
    require_once("identificadores.php");

    class Tripulacao {
        private RegistroDeTripulante $piloto;
        private RegistroDeTripulante $copiloto;
        private array $comissarios;
        private bool $trancado;

        public function __construct(RegistroDeTripulante $piloto = null, 
                                    RegistroDeTripulante $copiloto = null, 
                                    array $comissarios = null) {
            $this->piloto = $piloto;
            $this->copiloto = $copiloto;
            $this->comissarios = $comissarios ?? []; 
            $this->trancado = false;
        }

        public function getPiloto(){
            return $this->piloto;
        }

        public function getCopiloto(){
            return $this->copiloto;
        }

        public function getComissarios(){
            return $this->comissarios;
        }

        public function setPiloto(RegistroDeTripulante $piloto){
            if ($this->piloto != null){
                throw new Exception("Piloto só pode ser setado mais de uma vez.");
            }
            $this->piloto = $piloto;
        }

        public function setCopiloto(RegistroDeTripulante $copiloto){
            if ($this->copiloto != null){
                throw new Exception("Copiloto só pode ser setado mais de uma vez.");
            }
            $this->copiloto = $copiloto;
        }

        public function setComissarios(array $comissarios){
            if ($this->comissarios != null){
                throw new Exception("Array comissários não pode ser setado mais de uma vez.")
            }

            $this->comissarios = $comissarios;
        }

        public function addComissario(RegistroDeTripulante $comissario){
            $this->comissarios[] = $comissario;
        }

    
        public function validar(): void {
            if ($this->piloto == null){
                throw new Exception("Não há piloto na tripulação.");
            }

            if ($this->copiloto == null){
                throw new Exception("Não há copiloto na tripulação.");
            }

            if(count($this->comissarios) < 2){
                throw new Exception("Quantidade mínima de comissários não foi atingida.");
            }
        }

        public function trancar(): void {
            if ($this->trancado){
                throw new Exception ("Não é possível trancar duas vezes.");
            }

            $this->validar();
            $this->trancado = true;
        }
    }

?>