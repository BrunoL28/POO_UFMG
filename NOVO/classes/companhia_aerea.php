<?php

require_once 'aeronave.php';
require_once 'passageiro.php';
require_once 'franquia_de_bagagem.php';
require_once 'identificadores.php';
require_once 'passagem.php';
require_once 'persist.php';
require_once 'temporal.php';
require_once 'viagem.php';
require_once 'viagem_builder.php';
require_once 'voo.php';
require_once 'tripulante.php';

class CompanhiaAerea extends Persist
{
    private string $nome;
    private string $codigo;
    private string $razao_social;
    private SiglaCompanhiaAerea $sigla;
    /**
     * @var HashMap<RegistroDeAeronave, Aeronave>
     */
    private HashMap $aeronaves;
    /**
     * @var HashMap<CodigoVoo, Voo>
     */
    private HashMap $voos_planejados;
    /**
     * @var HashMap<Data, HashMap<RegistroDeViagem, ViagemBuilder>>
     */
    private HashMap $voos_em_venda;
    /**
     * @var HashMap<RegistroDeViagem, Viagem>
     */
    private HashMap $voos_executados;
    private GeradorDeRegistroDeViagem $gerador_de_registro_de_viagem;
    private GeradorDeRegistroDePassagem $gerador_de_registro_de_passagem;
    private float $tarifa_franquia;
    /**
     * @var HashMap<RegistroDePassagem, Passagem>
     */
    private HashMap $passagens;
    /**
     * @var HashMap<DocumentoPassageiro, Passageiro>
     */
    private HashMap $passageiros;
    /**
     * @var HashMap<RegistroDeTripulante, Tripulante>
     */
    private HashMap $tripulantes;
    private GeradorDeRegistroDeTripulante $gerador_de_registro_de_tripulante;
    private static $local_filename = "companhia_aerea.txt";

    public function __construct(string              $nome,
                                string              $codigo,
                                string              $razao_social,
                                SiglaCompanhiaAerea $sigla,
                                float               $tarifa_franquia,
                                                    ...$args)
    {

        $this->nome = $nome;
        $this->codigo = $codigo;
        $this->razao_social = $razao_social;
        $this->sigla = $sigla;
        $this->aeronaves = new HashMap();
        $this->voos_planejados = new HashMap();
        $this->voos_em_venda = new HashMap();
        $this->voos_executados = new HashMap();
        $this->gerador_de_registro_de_viagem = new GeradorDeRegistroDeViagem();
        $this->gerador_de_registro_de_passagem = new GeradorDeRegistroDePassagem();
        $this->tarifa_franquia = $tarifa_franquia;
        $this->passagens = new HashMap();
        $this->passageiros = new HashMap();
        $this->tripulantes = new HashMap();
        $this->gerador_de_registro_de_tripulante = new GeradorDeRegistroDeTripulante();
        parent::__construct(...$args);
    }

    public function getNome(): string
    {
        return $this->nome;
    }

    public function getCodigo(): string
    {
        return $this->codigo;
    }

    public function getRazaoSocial(): string
    {
        return $this->razao_social;
    }

    public function getSigla(): SiglaCompanhiaAerea
    {
        return $this->sigla;
    }

    public function getTarifaFranquia(): float
    {
        return $this->tarifa_franquia;
    }

    public static function getFilename()
    {
        return self::$local_filename;
    }

    /**
     * Procura de voos
     */

    /**
     * @param Data $data
     * @param SiglaAeroporto $aeroporto_de_saida
     * @param SiglaAeroporto $aeroporto_de_chegada
     * @return CodigoVoo[]
     * @throws EquatableTypeException
     */
    private function encontrarVoosSemConexao(Data $data, SiglaAeroporto $aeroporto_de_saida, SiglaAeroporto $aeroporto_de_chegada): array
    {
        /**
         * @var CodigoVoo[] $voos
         */
        $voos = [];

        $voo_desejado = function (Voo $voo) use ($data, $aeroporto_de_saida, $aeroporto_de_chegada) {
            if (!in_array($data->getDiaDaSemana(), $voo->getDiasDaSemana()))
                return false;
            if (!$aeroporto_de_saida->eq($voo->getAeroportoSaida()))
                return false;
            if (!$aeroporto_de_chegada->eq($voo->getAeroportoChegada()))
                return false;
            return true;
        };

        /**
         * @var Voo $voo
         */
        foreach (array_filter($this->voos_planejados->values(), $voo_desejado) as $voo) {
            $voos[] = $voo->getCodigo();
        }

        return $voos;
    }

    /**
     * @param Data $data
     * @param SiglaAeroporto $aeroporto_de_saida
     * @param SiglaAeroporto $aeroporto_de_chegada
     * @return CodigoVoo[][]
     * @throws ComparableTypeException
     * @throws EquatableTypeException
     */
    private function encontrarVoosComConexao(Data $data, SiglaAeroporto $aeroporto_de_saida, SiglaAeroporto $aeroporto_de_chegada): array
    {
        /**
         * @var CodigoVoo[][] $voos
         */
        $voos = [];

        $voo_intermediario_desejado = function (Voo $voo) use ($data, $aeroporto_de_saida) {
            if (!in_array($data->getDiaDaSemana(), $voo->getDiasDaSemana())) {
                return false;
            }
            if (!$aeroporto_de_saida->eq($voo->getAeroportoSaida())) {
                return false;
            }
            return true;
        };

        $voo_final_desejado = function (Voo $voo, Tempo $hora_de_chegada) use ($data, $aeroporto_de_chegada) {
            if (!in_array($data->getDiaDaSemana(), $voo->getDiasDaSemana())) {
                return false;
            }
            if (!$voo->getAeroportoChegada()->eq($aeroporto_de_chegada)) {
                return false;
            }
            $tempo_da_conexao = $hora_de_chegada->add(Duracao::meiaHora());
            if ($voo->getHoraDePartida()->gt($tempo_da_conexao)) {
                return false;
            }
            return true;
        };

        /**
         * @var Voo $voo_intermediario
         */
        foreach (array_filter($this->voos_planejados->values(), $voo_intermediario_desejado) as $voo_intermediario) {
            /**
             * @var Voo $voo_final
             */
            foreach (array_filter($this->voos_planejados->values(), $voo_final_desejado) as $voo_final) {
                $voos[] = [$voo_intermediario->getCodigo(), $voo_final->getCodigo()];
            }
        }

        return $voos;
    }

    /**
     * @param bool $cliente_vip
     * @param Data $data
     * @param SiglaAeroporto $aeroporto_de_saida
     * @param SiglaAeroporto $aeroporto_de_chegada
     * @param FranquiasDeBagagem $franquias
     * @return CodigoVoo[]
     * @throws ComparableTypeException
     * @throws EquatableTypeException
     */
    private function encontrarMelhorVoo(bool $cliente_vip, Data $data, SiglaAeroporto $aeroporto_de_saida, SiglaAeroporto $aeroporto_de_chegada, FranquiasDeBagagem $franquias)
    {
        /**
         * @var CodigoVoo[] $voos_sem_conexao
         */
        $voos_sem_conexao = $this->encontrarVoosSemConexao($data, $aeroporto_de_saida, $aeroporto_de_chegada);
        $melhor_tarifa = INF;

        if (count($voos_sem_conexao) != 0) {
            /**
             * @var Voo $melhor_voo
             */
            $melhor_voo = null;

            /**
             * @var CodigoVoo $codigo_voo
             */
            foreach ($voos_sem_conexao as $codigo_voo) {
                /**
                 * @var Voo $voo
                 */
                $voo = $this->voos_planejados->get($codigo_voo);
                $tarifa = $voo->calculaTarifa($cliente_vip, $franquias, $this->tarifa_franquia);

                if ($tarifa >= $melhor_tarifa)
                    continue;

                $melhor_voo = $voo;
                $melhor_tarifa = $tarifa;
            }

            return [$melhor_voo->getCodigo()];
        }

        /**
         * @var CodigoVoo[][] $pares_de_voos
         */
        $pares_de_voos = $this->encontrarVoosComConexao($data, $aeroporto_de_saida, $aeroporto_de_chegada);

        if (count($pares_de_voos) == 0) {
            return [];
        }

        /**
         * @var Voo $melhor_voo_intermediario
         */
        $melhor_voo_intermediario = null;

        /**
         * @var Voo $melhor_voo_final
         */
        $melhor_voo_final = null;

        foreach ($pares_de_voos as $par_de_voos) {
            [$codigo_voo_intermediario, $codigo_voo_final] = $par_de_voos;
            /**
             * @var Voo $voo_intermediario
             */
            $voo_intermediario = $this->voos_planejados->get($codigo_voo_intermediario);
            /**
             * @var Voo $voo_final
             */
            $voo_final = $this->voos_planejados->get($codigo_voo_final);
            $tarifa_intermediario = $voo_intermediario->calculaTarifa($cliente_vip, $franquias, $this->tarifa_franquia);
            $tarifa_final = $voo_final->calculaTarifa($cliente_vip, $franquias, $this->tarifa_franquia);
            $tarifa = $tarifa_intermediario + $tarifa_final;

            if ($tarifa >= $melhor_tarifa) {
                continue;
            }

            $melhor_voo_intermediario = $voo_intermediario;
            $melhor_voo_final = $voo_final;
            $melhor_tarifa = $tarifa;
        }

        return [$melhor_voo_intermediario->getCodigo(), $melhor_voo_final->getCodigo()];
    }

    /**
     * Bookkeeping para ViagemBuilder
     */

    public function adicionarViagensEmVenda(): void
    {
        /**
         * @var Data[] $datas_atuais
         */
        $datas_atuais = $this->voos_em_venda->keys();
        $hoje = Data::hoje();
        /**
         * @var Data[] $datas_alvo
         */
        $datas_alvo = array_map(function (int $i) use ($hoje) {
            return $hoje->add(Duracao::umDia()->mul($i));
        }, range(0, 30));
        /**
         * @var Data[] $datas_nao_preenchidas
         */
        $datas_nao_preenchidas = array_diff_equatable($datas_alvo, $datas_atuais);

        foreach ($datas_nao_preenchidas as $data) {
            /**
             * @var Voo[] $voos_nesse_dia_da_semana
             */
            $voos_nesse_dia_da_semana = array_filter($this->voos_planejados->values(), function (Voo $voo) use ($data) {
                return in_array($data->getDiaDaSemana(), $voo->getDiasDaSemana());
            });
            /**
             * @var HashMap<RegistroDeViagem, Viagem> $viagens_nesse_dia_da_semana
             */
            $viagens_nesse_dia_da_semana = $this->voos_em_venda->put($data, new HashMap());

            foreach ($voos_nesse_dia_da_semana as $voo_que_ira_acontecer) {
                $viagem_builder = (new ViagemBuilder())
                    ->addTarifaFranquia($this->tarifa_franquia)
                    ->adicionarGeradorDeRegistro($this->gerador_de_registro_de_viagem)
                    ->gerarRegistro()
                    ->addData($data)
                    ->addVoo($voo_que_ira_acontecer);

                $registro_da_viagem = $viagem_builder->getRegistro();
                $viagens_nesse_dia_da_semana->put($registro_da_viagem, $viagem_builder);
            }
        }
    }
    public function registrarAeronaveNaViagem(RegistroDeViagem $registroDeViagem, RegistroDeAeronave $registroDeAeronave) {
        if (!$this->aeronaves->containsKey($registroDeAeronave)) {
            throw new Exception("Aeronave não presente na companhia");
        }
        /**
         * @var Aeronave $aeronave
         */
        $aeronave = $this->aeronaves->get($registroDeAeronave);
        $vb = $this->findRequiredViagemBuilder($registroDeViagem);
        $vb->addAeronave($aeronave);
    }

    public function registrarTripulanteNaViagem(RegistroDeViagem $registroDeViagem, RegistroDeTripulante $registroDeTripulante) {
        if (!$this->tripulantes->containsKey($registroDeTripulante)) {
            throw new Exception("Tripulante não presente na companhia");
        }
        /**
         * @var Tripulante $tripulante
         */
        $tripulante = $this->tripulantes->get($registroDeTripulante);
        $vb = $this->findRequiredViagemBuilder($registroDeViagem);
        $vb->addTripulante($tripulante);
    }

    public function registrarQueViagemAconteceu(DataTempo $hora_de_partida, DataTempo $hora_de_chegada, RegistroDeViagem $registro_de_viagem)
    {
        $builder = $this->removeViagemBuilder($registro_de_viagem);
        $builder->addHoraDePartidaEHoraDeChegada($hora_de_partida, $hora_de_chegada);
        try {
            $viagem = $builder->build();
        } catch (Throwable $e) {
            $this->voos_em_venda->get($builder->getData())->put($builder->getRegistro(), $builder);
            throw new Exception("Não foi possivel buildar a Viagem", $e->getCode(), $e);
        }
        $this->voos_executados->put($viagem->getRegistro(), $viagem);
        /**
         * @var Assento $assento
         */
        foreach ($viagem->getAssentos()->values() as $assento) {
            if ($assento->vazio())
                continue;

            $registro_passagem = $assento->getPassagem();
            $passagem = $this->passagens->get($registro_passagem);
            $passagem->acionar_evento(Evento::CONCLUIR);
        }
    }

    /**
     * Metodos para passagens
     */

    public function cancelarPassagem(RegistroDePassagem $passagem): void
    {
        if (!$this->passagens->containsKey($passagem)) {
            throw new Exception("Passagem não está na companhia");
        }

        $passagem = $this->passagens->get($passagem);

        if (!$passagem->acionar_evento(Evento::CANCELAR)) {
            throw new Exception("A passagem não pode ser cancelada agora");
        }

        $data = $passagem->getData();

        foreach ($passagem->getAssentos()->entries() as $entry) {
            /**
             * @var RegistroDeViagem $registro_viagem
             */
            $registro_viagem = $entry->key;
            /**
             * @var CodigoDoAssento $codigo_assento
             */
            $codigo_assento = $entry->value;
            /**
             * @var HashMap<RegistroDeViagem, ViagemBuilder> $voos_em_venda_na_data
             */
            $voos_em_venda_na_data = $this->voos_em_venda->get($data);

            if (!$voos_em_venda_na_data->containsKey($registro_viagem)) {
                throw new Exception("Não é possível cancelar uma viagem que já ocorreu");
            }

            $viagem_builder = $this->findViagemBuilder($registro_viagem, $data);
            $viagem_builder->liberarAssento($passagem->getRegistro(), $codigo_assento);
        }
    }

    public function abrirCheckInParaPassagens(RegistroDePassagem ...$args)
    {
        $twoDays = new Duracao(2, 0);
        $hoje = Data::hoje();
        if (!empty($args)) {
            foreach ($args as $registro_passagem) {
                if (!$this->passagens->containsKey($registro_passagem)) {
                    throw new Exception("Passagem não está na companhia");
                }

                /**
                 * @var Passagem $passagem
                 */
                $passagem = $this->passagens->get($registro_passagem);

                $delta = $passagem->getData()->dt($hoje);
                if ($delta->st($twoDays)) {
                    continue;
                }

                throw new Exception("Passagem está a mais de 48h de distância");
            }

            foreach ($args as $registro_passagem) {
                /**
                 * @var Passagem $passagem
                 */
                $passagem = $this->passagens->get($registro_passagem);
                $passagem->acionarEvento(Evento::ABRIR_CHECK_IN);
            }

            return;
        }

        /**
         * @var Passagem $passagem
         */
        foreach ($this->passagens->values() as $passagem) {
            $delta = $passagem->getData()->dt($hoje);
            if ($delta->st($twoDays)) {
                $passagem->acionarEvento(Evento::ABRIR_CHECK_IN);
            }
        }
    }

    /** Retorna o historico de viagens de um passageiro
     * @param DocumentoPassageiro $documentoPassageiro
     * @return Viagem[]
     * @throws ComparableTypeException
     * @throws EquatableTypeException
     */
    public function acessarHistoricoDeViagens(DocumentoPassageiro $documentoPassageiro): array
    {
        if (!$this->passageiros->containsKey($documentoPassageiro)) {
            throw new Exception("Passageiro nao cadastrado");
        }

        /**
         * @var Passageiro $passageiro
         */
        $passageiro = $this->passageiros->get($documentoPassageiro);
        $registros_de_passagens = $passageiro->getPassagens();
        /**
         * @var Passagem[] $passagens
         */
        $passagens = array_map(function (RegistroDePassagem $passagem) {
            return $this->passagens->get($passagem);
        }, $registros_de_passagens);

        /**
         * @var Viagem[] $viagens
         */
        $viagens = [];

        foreach ($passagens as $passagem) {
            /**
             * @var RegistroDeViagem[] $registros_de_viagens
             */
            $registros_de_viagens = $passagem->getAssentos()->keys();
            /**
             * @var Viagem[] $viagens_na_passagem
             */
            $viagens_na_passagem = array_map(function (RegistroDeViagem $registro_de_viagem) {
                return $this->voos_executados->get($registro_de_viagem);
            }, $registros_de_viagens);

            foreach ($viagens_na_passagem as $viagem) {
                $viagens[] = $viagem;
            }
        }

        usort($viagens, function (Viagem $a, Viagem $b): int {
            if ($a->getHoraDePartida()->eq($b->getHoraDePartida())) {
                return 0;
            }
            return $a->getHoraDePartida()->st($b->getHoraDePartida()) ? -1 : 1;
        });
        return $viagens;
    }

    function fazerCheckIn(RegistroDePassagem $passagem)
    {
        if (!$this->passagens->containsKey($passagem)) {
            throw new Exception("Passagem não está na companhia");
        }

        /**
         * @var Passagem $passagem
         */
        $passagem = $this->passagens->get($passagem);

        $twoDays = new Duracao(2, 0);
        $hoje = Data::hoje();
        $delta = $passagem->getData()->dt($hoje);
        if ($delta->st($twoDays)) {
            $passagem->acionarEvento(Evento::ABRIR_CHECK_IN);
        }

        if (!$passagem->acionarEvento(Evento::FAZER_CHECK_IN)) {
            throw new Exception("Não é possível fazer check-in agora");
        }
    }

    function comprarPassagem(DocumentoPassageiro $documentoPassageiro, Data $data, SiglaAeroporto $aeroporto_de_saida, SiglaAeroporto $aeroporto_de_chegada, FranquiasDeBagagem $franquias, ?CodigoDoAssento $assento = null)
    {
        $this->adicionarViagensEmVenda();
        if (!$this->passageiros->containsKey($documentoPassageiro)) {
            throw new Exception("Cliente nao cadastrado");
        }

        /**
         * @var Passageiro $passageiro
         */
        $passageiro = $this->passageiros->get($documentoPassageiro);
        $voos = $this->encontrarMelhorVoo($passageiro instanceof PassageiroVip, $data, $aeroporto_de_saida, $aeroporto_de_chegada, $franquias);

        if (count($voos) == 0) {
            return null;
        }

        /**
         * @var ViagemBuilder[] $viagem_builders
         */
        $viagem_builders = array_map(function (CodigoVoo $codigoVoo) use ($data) {
            $vb = $this->findViagemBuilderByDataECodigoVoo($data, $codigoVoo);
            if (is_null($vb)) {
                throw new Exception("Viagem builder não encontrado");
            }
            return $vb;
        }, $voos);

        foreach ($viagem_builders as $viagem_builder) {
            if ($assento === null) {
                if (!$viagem_builder->temAssentosLiberados()) {
                    return null;
                }
            } else if (!$viagem_builder->assentoEstaLiberado($assento)) {
                return null;
            }

            if (!$viagem_builder->temCargaDisponivelParaFranquias($franquias)) {
                return null;
            }
        }

        $registro_passagem = $this->gerador_de_registro_de_passagem->gerar();
        /**
         * @var HashMap<RegistroDeViagem, CodigoDoAssento> $viagens_assentos
         */
        $viagens_assentos = new HashMap();
        $valor_total = 0.0;

        foreach ($viagem_builders as $viagem_builder) {
            $assento_desejado = $assento ?? $viagem_builder->codigoAssentoLiberado();
            $valor = $viagem_builder->reservarAssento($passageiro instanceof PassageiroVip, $registro_passagem, $franquias, $assento_desejado);
            $valor_total += $valor;
            $viagens_assentos->put($viagem_builder->getRegistro(), $assento_desejado);
        }

        $status = new PassagemCheckInNaoAberto();
        $twoDays = new Duracao(2, 0);
        $hoje = Data::hoje();
        $delta = $data->dt($hoje);
        if ($delta->st($twoDays)) {
            $status = $status->abrir_check_in();
        }

        $primeiro_voo = $voos[0];
        $ultimo_voo = end($voos);
        $passagem = new Passagem(
            $registro_passagem,
            $this->voos_planejados->get($primeiro_voo)->getAeroportoSaida(),
            $this->voos_planejados->get($ultimo_voo)->getAeroportoChegada(),
            $this->sigla,
            $documentoPassageiro,
            $data,
            $valor_total,
            0,
            $viagens_assentos,
            DataTempo::agora(),
            $status
        );

        $passageiro->addPassagem($registro_passagem);
        $this->passagens->put($registro_passagem, $passagem);
        return $registro_passagem;
    }

    /**
     * Procura de ViagemBuilder
     */

    /** Remove um viagem builder pelo RegistroDeViagem e arremessa Exception se não encontrar
     * @param RegistroDeViagem $registroDeViagem
     * @return ViagemBuilder
     * @throws Exception
     */
    private function removeViagemBuilder(RegistroDeViagem $registroDeViagem): ViagemBuilder
    {
        $vb = $this->findRequiredViagemBuilder($registroDeViagem);
        $data = $vb->getData();
        if (!$this->voos_em_venda->get($data)->remove($registroDeViagem)) {
            throw new Exception("Não foi possivel remover o viagem builder");
        }
        return $vb;
    }

    /** Encontra um viagem builder pelo RegistroDeViagem e arremessa Exception se não encontrar
     * @param RegistroDeViagem $registroDeViagem
     * @param Data|null $data
     * @return ViagemBuilder
     */
    private function findRequiredViagemBuilder(RegistroDeViagem $registroDeViagem, ?Data $data = null): ViagemBuilder
    {
        $vb = $this->findViagemBuilder($registroDeViagem, $data);
        if (is_null($vb)) {
            throw new Exception("Viagem builder não encontrado");
        }
        return $vb;
    }

    /** Encontra um viagem builder pelo RegistroDeViagem e opcionalmente Data e retona null se não encontrar
     * @param RegistroDeViagem $registroDeViagem
     * @param Data|null $data
     * @return ViagemBuilder|null
     */
    private function findViagemBuilder(RegistroDeViagem $registroDeViagem, ?Data $data = null): ?ViagemBuilder {
        if (!is_null($data)) {
            if (!$this->voos_em_venda->containsKey($data)) {
                return null;
            }
            /**
             * @var HashMap<RegistroDeViagem, ViagemBuilder> $registroViagemBuilder
             */
            $registroViagemBuilder = $this->voos_em_venda->get($data);
            if (!$registroViagemBuilder->containsKey($registroDeViagem)) {
                return null;
            }
            /**
             * @var ViagemBuilder $viagemBuilder
             */
            $viagemBuilder = $registroViagemBuilder->get($registroDeViagem);
            return $viagemBuilder;
        }
        /**
         * @var HashMap<RegistroDeViagem, ViagemBuilder> $registroViagemBuilder
         */
        foreach ($this->voos_em_venda->values() as $registroViagemBuilder) {
            if ($registroViagemBuilder->containsKey($registroDeViagem)) {
                return $registroViagemBuilder->get($registroDeViagem);
            }
        }
        return null;
    }

    /** Encontra um viagem builder pela Data e CodigoVoo e arremessa Exception se não encontrar
     * @param Data $data
     * @param CodigoVoo $codigoVoo
     * @return ViagemBuilder
     */
    private function findRequiredViagemBuilderByDataECodigoVoo(Data $data, CodigoVoo $codigoVoo): ViagemBuilder
    {
        $vb = $this->findViagemBuilderByDataECodigoVoo($data, $codigoVoo);
        if (is_null($vb)) {
            throw new Exception("Viagem builder não encontrado");
        }
        return $vb;
    }

    /** Encontra um viagem builder pela Data e CodigoVoo e retorna null se não encontrar
     * @param Data $data
     * @param CodigoVoo $codigoVoo
     * @return ViagemBuilder|null
     */
    private function findViagemBuilderByDataECodigoVoo(Data $data, CodigoVoo $codigoVoo): ?ViagemBuilder {
        if (!$this->voos_em_venda->containsKey($data)) {
            return null;
        }
        /**
         * @var HashMap<RegistroDeViagem, ViagemBuilder> $registroViagemBuilder
         */
        foreach ($this->voos_em_venda->values() as $registroViagemBuilder) {
            /**
             * @var ViagemBuilder $viagemBuilder
             */
            foreach ($registroViagemBuilder->values() as $viagemBuilder) {
                if ($viagemBuilder->getCodigoDoVoo()->eq($codigoVoo)) {
                    return $viagemBuilder;
                }
            }
        }
        return null;
    }

    /**
     * CRUD
     */

    public function registrarVoo(
        int                $numero,
        SiglaAeroporto     $aeroporto_de_saida,
        SiglaAeroporto     $aeroporto_de_chegada,
        Tempo              $hora_de_partida,
        Duracao            $duracao_estimada,
        array              $dias_da_semana,
        RegistroDeAeronave $aeronave_padrao,
        int                $capacidade_passageiros,
        float              $capacidade_carga,
        float              $tarifa,
        int                $pontuacaoMilhagem): Voo
    {
        $voo = new Voo(new CodigoVoo($this->sigla, $numero),
            $aeroporto_de_saida,
            $aeroporto_de_chegada,
            $hora_de_partida,
            $duracao_estimada,
            $dias_da_semana,
            $aeronave_padrao,
            $capacidade_passageiros,
            $capacidade_carga,
            $tarifa,
            $pontuacaoMilhagem
        );
        if ($this->voos_planejados->containsKey($voo->getCodigo())) {
            throw new Exception("Voo já presente");
        }
        # Adicionar aos voos planejados
        $this->voos_planejados->put($voo->getCodigo(), $voo);
        # Adicionar aos voos em venda que já estão presentes
        foreach ($this->voos_em_venda->entries() as $entry) {
            $data = $entry->key;
            $viagem_builders = $entry->value;
            if (!in_array($data->getDiaDaSemana(), $voo->getDiasDaSemana())) {
                continue;
            }
            $viagem_builder = (new ViagemBuilder())
                ->addTarifaFranquia($this->tarifa_franquia)
                ->adicionarGeradorDeRegistro($this->gerador_de_registro_de_viagem)
                ->gerarRegistro()
                ->addData($data)
                ->addVoo($voo);

            $registro_da_viagem = $viagem_builder->getRegistro();
            $viagem_builders->put($registro_da_viagem, $viagem_builder);
        }
        return $voo;
    }

    public function encontrarVoo(CodigoVoo $voo): ?Voo
    {
        return $this->voos_planejados->get($voo);
    }

    public function adicionarPassageiro(Passageiro $passageiro): void
    {
        if ($this->passageiros->containsKey($passageiro->getDocumento())) {
            throw new Exception("Passageiro já presente");
        }
        $this->passageiros->put($passageiro->getDocumento(), $passageiro);
    }

    public function encontrarPassageiro(DocumentoPassageiro $passageiro): ?Passageiro
    {
        return $this->passageiros->get($passageiro);
    }

    public function registrarTripulante(
        string              $nome,
        string              $sobrenome,
        CPF                 $cpf,
        Nacionalidade       $nacionalidade,
        DataTempo           $data_de_nascimento,
        Email               $email,
        string              $cht,
        Endereco            $endereco,
        SiglaCompanhiaAerea $companhia,
        SiglaAeroporto      $aeroporto_base,
        Cargo               $cargo): Tripulante
    {
        $registro = $this->gerador_de_registro_de_tripulante->gerar();
        $tripulante = new Tripulante(
            $nome,
            $sobrenome,
            $cpf,
            $nacionalidade,
            $data_de_nascimento,
            $email,
            $cht,
            $endereco,
            $companhia,
            $aeroporto_base,
            $cargo,
            $registro,
        );
        $this->tripulantes->put($registro, $tripulante);
        return $tripulante;
    }

    public function encontrarTripulante(RegistroDeTripulante $tripulante): ?Tripulante
    {
        return $this->tripulantes->get($tripulante);
    }

    public function registrarAeronave(
        string             $fabricante,
        string             $modelo,
        int                $capacidade_passageiros,
        float              $capacidade_carga,
        RegistroDeAeronave $registro)
    {
        if ($this->aeronaves->containsKey($registro)) {
            throw new Exception("Aeronave já presente");
        }
        $aeronave = new Aeronave(
            $this->sigla,
            $fabricante,
            $modelo,
            $capacidade_passageiros,
            $capacidade_carga, $registro
        );
        $this->aeronaves->put($registro, $aeronave);
        return $aeronave;
    }

    public function encontrarAeronave(RegistroDeAeronave $registro): ?Aeronave
    {
        return $this->aeronaves->get($registro);
    }

    public function encontrarPassagem(RegistroDePassagem $registro): ?Passagem {
        return $this->passagens->get($registro);
    }
}

?>