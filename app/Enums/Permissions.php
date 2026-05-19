<?php

namespace App\Enums;

/**
 * Registro central de permissões do sistema.
 *
 * Convenção: <modulo>.<acao>
 *
 * Acrescente novas permissões em `groups()` agrupadas por módulo.
 * O seeder utiliza `values()` para criar/atualizar de forma idempotente.
 */
final class Permissions
{
    public const USUARIOS_VISUALIZAR = 'usuarios.visualizar';

    public const USUARIOS_CRIAR = 'usuarios.criar';

    public const USUARIOS_EDITAR = 'usuarios.editar';

    public const USUARIOS_EXCLUIR = 'usuarios.excluir';

    public const USUARIOS_RESETAR_SENHA = 'usuarios.resetar-senha';

    public const USUARIOS_DESATIVAR = 'usuarios.desativar';

    public const USUARIOS_REATIVAR = 'usuarios.reativar';

    public const GRUPOS_PERMISSOES_VISUALIZAR = 'grupos-permissoes.visualizar';

    public const GRUPOS_PERMISSOES_CRIAR = 'grupos-permissoes.criar';

    public const GRUPOS_PERMISSOES_EDITAR = 'grupos-permissoes.editar';

    public const EMPRESAS_VISUALIZAR = 'empresas.visualizar';

    public const EMPRESAS_CRIAR = 'empresas.criar';

    public const EMPRESAS_EDITAR = 'empresas.editar';

    public const EMPRESAS_INATIVAR = 'empresas.inativar';

    public const EMPRESAS_REATIVAR = 'empresas.reativar';

    public const EMPRESAS_IMPORTAR = 'empresas.importar';

    public const EMPRESAS_IMPORTAR_CONFIRMAR = 'empresas.importar-confirmar';

    public const EMPRESAS_HISTORICO = 'empresas.historico';

    public const EMPRESAS_EXPORTAR_PDF = 'empresas.exportar-pdf';

    public const ESTADOS_VISUALIZAR = 'estados.visualizar';

    public const ESTADOS_CRIAR = 'estados.criar';

    public const ESTADOS_EDITAR = 'estados.editar';

    public const ESTADOS_INATIVAR = 'estados.inativar';

    public const ESTADOS_REATIVAR = 'estados.reativar';

    public const ESTADOS_IMPORTAR = 'estados.importar';

    public const ESTADOS_IMPORTAR_CONFIRMAR = 'estados.importar-confirmar';

    public const UNIDADES_NEGOCIO_VISUALIZAR = 'unidades-negocio.visualizar';

    public const UNIDADES_NEGOCIO_CRIAR = 'unidades-negocio.criar';

    public const UNIDADES_NEGOCIO_EDITAR = 'unidades-negocio.editar';

    public const UNIDADES_NEGOCIO_INATIVAR = 'unidades-negocio.inativar';

    public const UNIDADES_NEGOCIO_ATIVAR = 'unidades-negocio.ativar';

    public const UNIDADES_NEGOCIO_IMPORTAR = 'unidades-negocio.importar';

    public const UNIDADES_NEGOCIO_IMPORTAR_CONFIRMAR = 'unidades-negocio.importar-confirmar';

    public const UNIDADES_NEGOCIO_HISTORICO = 'unidades-negocio.historico';

    public const UNIDADES_NEGOCIO_EXPORTAR_PDF = 'unidades-negocio.exportar-pdf';

    public const FORNECEDORES_VISUALIZAR = 'fornecedores.visualizar';

    public const FORNECEDORES_CRIAR = 'fornecedores.criar';

    public const FORNECEDORES_EDITAR = 'fornecedores.editar';

    public const FORNECEDORES_IMPORTAR = 'fornecedores.importar';

    public const FORNECEDORES_IMPORTAR_CONFIRMAR = 'fornecedores.importar-confirmar';

    public const FORNECEDORES_HISTORICO = 'fornecedores.historico';

    public const FORNECEDORES_EXPORTAR_PDF = 'fornecedores.exportar-pdf';

    public const VEICULOS_VISUALIZAR = 'veiculos.visualizar';

    public const VEICULOS_CRIAR = 'veiculos.criar';

    public const VEICULOS_EDITAR = 'veiculos.editar';

    public const VEICULOS_INATIVAR = 'veiculos.inativar';

    public const VEICULOS_REATIVAR = 'veiculos.reativar';

    public const VEICULOS_IMPORTAR = 'veiculos.importar';

    public const VEICULOS_IMPORTAR_CONFIRMAR = 'veiculos.importar-confirmar';

    public const VEICULOS_HISTORICO = 'veiculos.historico';

    public const VEICULOS_EXPORTAR_PDF = 'veiculos.exportar-pdf';

    public const CLIENTES_VISUALIZAR = 'clientes.visualizar';

    public const CLIENTES_CRIAR = 'clientes.criar';

    public const CLIENTES_EDITAR = 'clientes.editar';

    public const CLIENTES_IMPORTAR = 'clientes.importar';

    public const CLIENTES_IMPORTAR_CONFIRMAR = 'clientes.importar-confirmar';

    public const CLIENTES_HISTORICO = 'clientes.historico';

    public const CLIENTES_EXPORTAR_PDF = 'clientes.exportar-pdf';

    public const FRUTAS_VISUALIZAR = 'frutas.visualizar';

    public const FRUTAS_CRIAR = 'frutas.criar';

    public const FRUTAS_EDITAR = 'frutas.editar';

    public const FRUTAS_IMPORTAR = 'frutas.importar';

    public const FRUTAS_IMPORTAR_CONFIRMAR = 'frutas.importar-confirmar';

    public const FRUTAS_ICMS_VISUALIZAR = 'frutas.icms.visualizar';

    public const FRUTAS_ICMS_CRIAR = 'frutas.icms.criar';

    public const FRUTAS_ICMS_EDITAR = 'frutas.icms.editar';

    public const FRUTAS_ICMS_IMPORTAR = 'frutas.icms.importar';

    public const FRUTAS_ICMS_IMPORTAR_CONFIRMAR = 'frutas.icms.importar-confirmar';

    public const FRUTAS_HISTORICO = 'frutas.historico';

    public const FRUTAS_EXPORTAR_PDF = 'frutas.exportar-pdf';

    public const FRETES_VISUALIZAR = 'fretes.visualizar';

    public const FRETES_CRIAR = 'fretes.criar';

    public const FRETES_EDITAR = 'fretes.editar';

    public const FRETES_IMPORTAR = 'fretes.importar';

    public const FRETES_IMPORTAR_CONFIRMAR = 'fretes.importar-confirmar';

    public const FRETES_HISTORICO = 'fretes.historico';

    public const FRETES_EXPORTAR_PDF = 'fretes.exportar-pdf';

    public const PRACAS_VISUALIZAR = 'pracas.visualizar';

    public const PRACAS_CRIAR = 'pracas.criar';

    public const PRACAS_EDITAR = 'pracas.editar';

    public const PRACAS_IMPORTAR = 'pracas.importar';

    public const PRACAS_IMPORTAR_CONFIRMAR = 'pracas.importar-confirmar';

    public const PRACAS_HISTORICO = 'pracas.historico';

    public const PRACAS_EXPORTAR_PDF = 'pracas.exportar-pdf';

    public const ESTOQUES_VISUALIZAR = 'estoques.visualizar';

    public const ESTOQUES_MOVIMENTAR = 'estoques.movimentar';

    public const ESTOQUES_IMPORTAR = 'estoques.importar';

    public const ESTOQUES_IMPORTAR_CONFIRMAR = 'estoques.importar-confirmar';

    public const ESTOQUES_EXPORTAR_PDF = 'estoques.exportar-pdf';

    public const MOVIMENTACOES_COMPRAS_VISUALIZAR = 'movimentacoes.compras.visualizar';

    public const MOVIMENTACOES_COMPRAS_CRIAR = 'movimentacoes.compras.criar';

    public const MOVIMENTACOES_COMPRAS_EDITAR = 'movimentacoes.compras.editar';

    public const MOVIMENTACOES_COMPRAS_CANCELAR_ADMIN = 'movimentacoes.compras.cancelar-admin';

    public const MOVIMENTACOES_TRANSFERENCIAS_VISUALIZAR = 'movimentacoes.transferencias.visualizar';

    public const MOVIMENTACOES_TRANSFERENCIAS_CRIAR = 'movimentacoes.transferencias.criar';

    public const MOVIMENTACOES_TRANSFERENCIAS_RECEBER = 'movimentacoes.transferencias.receber';

    public const MOVIMENTACOES_TRANSFERENCIAS_REENVIAR = 'movimentacoes.transferencias.reenviar';

    public const MOVIMENTACOES_TRANSFERENCIAS_CANCELAR = 'movimentacoes.transferencias.cancelar';

    public const MOVIMENTACOES_TRANSFERENCIAS_CANCELAR_ADMIN = 'movimentacoes.transferencias.cancelar-admin';

    public const MOVIMENTACOES_DOACOES_VISUALIZAR = 'movimentacoes.doacoes.visualizar';

    public const MOVIMENTACOES_DOACOES_CRIAR = 'movimentacoes.doacoes.criar';

    public const MOVIMENTACOES_DOACOES_EDITAR = 'movimentacoes.doacoes.editar';

    public const MOVIMENTACOES_DOACOES_CANCELAR_ADMIN = 'movimentacoes.doacoes.cancelar-admin';

    public const MOVIMENTACOES_DESCARTES_VISUALIZAR = 'movimentacoes.descartes.visualizar';

    public const MOVIMENTACOES_DESCARTES_CRIAR = 'movimentacoes.descartes.criar';

    public const MOVIMENTACOES_DESCARTES_EDITAR = 'movimentacoes.descartes.editar';

    public const MOVIMENTACOES_DESCARTES_CANCELAR_ADMIN = 'movimentacoes.descartes.cancelar-admin';

    public const MOVIMENTACOES_VENDAS_VISUALIZAR = 'movimentacoes.vendas.visualizar';

    public const MOVIMENTACOES_VENDAS_CRIAR = 'movimentacoes.vendas.criar';

    public const MOVIMENTACOES_VENDAS_EDITAR = 'movimentacoes.vendas.editar';

    public const MOVIMENTACOES_VENDAS_CANCELAR_ADMIN = 'movimentacoes.vendas.cancelar-admin';

    public const MOVIMENTACOES_DEVOLUCOES_VISUALIZAR = 'movimentacoes.devolucoes.visualizar';

    public const MOVIMENTACOES_DEVOLUCOES_CRIAR = 'movimentacoes.devolucoes.criar';

    public const MOVIMENTACOES_DEVOLUCOES_EDITAR = 'movimentacoes.devolucoes.editar';

    public const MOVIMENTACOES_DEVOLUCOES_CANCELAR_ADMIN = 'movimentacoes.devolucoes.cancelar-admin';

    public const MOVIMENTACOES_CONVERSOES_EMBALAGEM_VISUALIZAR = 'movimentacoes.conversoes-embalagem.visualizar';

    public const MOVIMENTACOES_CONVERSOES_EMBALAGEM_CRIAR = 'movimentacoes.conversoes-embalagem.criar';

    public const GRUPOS_VISUALIZAR = 'grupos.visualizar';

    public const GRUPOS_CRIAR = 'grupos.criar';

    public const GRUPOS_EDITAR = 'grupos.editar';

    public const GRUPOS_IMPORTAR = 'grupos.importar';

    public const GRUPOS_IMPORTAR_CONFIRMAR = 'grupos.importar-confirmar';

    public const GRUPOS_HISTORICO = 'grupos.historico';

    public const GRUPOS_EXPORTAR_PDF = 'grupos.exportar-pdf';

    public const GRUPOS_CONTRATO_VISUALIZAR = 'grupos-contrato.visualizar';

    public const GRUPOS_CONTRATO_CRIAR = 'grupos-contrato.criar';

    public const GRUPOS_CONTRATO_EDITAR = 'grupos-contrato.editar';

    public const GRUPOS_CONTRATO_MEMBROS = 'grupos-contrato.membros';

    public const GRUPOS_CONTRATO_DESCONTOS = 'grupos-contrato.descontos';

    public const GRUPOS_CONTRATO_HISTORICO = 'grupos-contrato.historico';

    /**
     * Permissões agrupadas por módulo (útil para UI/telas de gestão).
     *
     * @return array<string, list<string>>
     */
    public static function groups(): array
    {
        return [
            'Usuários' => [
                self::USUARIOS_VISUALIZAR,
                self::USUARIOS_CRIAR,
                self::USUARIOS_EDITAR,
                self::USUARIOS_EXCLUIR,
                self::USUARIOS_RESETAR_SENHA,
                self::USUARIOS_DESATIVAR,
                self::USUARIOS_REATIVAR,
            ],
            'Grupos de Permissões' => [
                self::GRUPOS_PERMISSOES_VISUALIZAR,
                self::GRUPOS_PERMISSOES_CRIAR,
                self::GRUPOS_PERMISSOES_EDITAR,
            ],
            'Empresas' => [
                self::EMPRESAS_VISUALIZAR,
                self::EMPRESAS_CRIAR,
                self::EMPRESAS_EDITAR,
                self::EMPRESAS_INATIVAR,
                self::EMPRESAS_REATIVAR,
                self::EMPRESAS_IMPORTAR,
                self::EMPRESAS_IMPORTAR_CONFIRMAR,
                self::EMPRESAS_HISTORICO,
                self::EMPRESAS_EXPORTAR_PDF,
            ],
            'Estados' => [
                self::ESTADOS_VISUALIZAR,
                self::ESTADOS_CRIAR,
                self::ESTADOS_EDITAR,
                self::ESTADOS_INATIVAR,
                self::ESTADOS_REATIVAR,
                self::ESTADOS_IMPORTAR,
                self::ESTADOS_IMPORTAR_CONFIRMAR,
            ],
            'Unidades de Negócio' => [
                self::UNIDADES_NEGOCIO_VISUALIZAR,
                self::UNIDADES_NEGOCIO_CRIAR,
                self::UNIDADES_NEGOCIO_EDITAR,
                self::UNIDADES_NEGOCIO_INATIVAR,
                self::UNIDADES_NEGOCIO_ATIVAR,
                self::UNIDADES_NEGOCIO_IMPORTAR,
                self::UNIDADES_NEGOCIO_IMPORTAR_CONFIRMAR,
                self::UNIDADES_NEGOCIO_HISTORICO,
                self::UNIDADES_NEGOCIO_EXPORTAR_PDF,
            ],
            'Fornecedores' => [
                self::FORNECEDORES_VISUALIZAR,
                self::FORNECEDORES_CRIAR,
                self::FORNECEDORES_EDITAR,
                self::FORNECEDORES_IMPORTAR,
                self::FORNECEDORES_IMPORTAR_CONFIRMAR,
                self::FORNECEDORES_HISTORICO,
                self::FORNECEDORES_EXPORTAR_PDF,
            ],
            'Veículos' => [
                self::VEICULOS_VISUALIZAR,
                self::VEICULOS_CRIAR,
                self::VEICULOS_EDITAR,
                self::VEICULOS_INATIVAR,
                self::VEICULOS_REATIVAR,
                self::VEICULOS_IMPORTAR,
                self::VEICULOS_IMPORTAR_CONFIRMAR,
                self::VEICULOS_HISTORICO,
                self::VEICULOS_EXPORTAR_PDF,
            ],
            'Clientes' => [
                self::CLIENTES_VISUALIZAR,
                self::CLIENTES_CRIAR,
                self::CLIENTES_EDITAR,
                self::CLIENTES_IMPORTAR,
                self::CLIENTES_IMPORTAR_CONFIRMAR,
                self::CLIENTES_HISTORICO,
                self::CLIENTES_EXPORTAR_PDF,
            ],
            'Grupos' => [
                self::GRUPOS_VISUALIZAR,
                self::GRUPOS_CRIAR,
                self::GRUPOS_EDITAR,
                self::GRUPOS_IMPORTAR,
                self::GRUPOS_IMPORTAR_CONFIRMAR,
                self::GRUPOS_HISTORICO,
                self::GRUPOS_EXPORTAR_PDF,
            ],
            'Grupos de Contrato' => [
                self::GRUPOS_CONTRATO_VISUALIZAR,
                self::GRUPOS_CONTRATO_CRIAR,
                self::GRUPOS_CONTRATO_EDITAR,
                self::GRUPOS_CONTRATO_MEMBROS,
                self::GRUPOS_CONTRATO_DESCONTOS,
                self::GRUPOS_CONTRATO_HISTORICO,
            ],
            'Frutas' => [
                self::FRUTAS_VISUALIZAR,
                self::FRUTAS_CRIAR,
                self::FRUTAS_EDITAR,
                self::FRUTAS_IMPORTAR,
                self::FRUTAS_IMPORTAR_CONFIRMAR,
                self::FRUTAS_ICMS_VISUALIZAR,
                self::FRUTAS_ICMS_CRIAR,
                self::FRUTAS_ICMS_EDITAR,
                self::FRUTAS_ICMS_IMPORTAR,
                self::FRUTAS_ICMS_IMPORTAR_CONFIRMAR,
                self::FRUTAS_HISTORICO,
                self::FRUTAS_EXPORTAR_PDF,
            ],
            'Fretes' => [
                self::FRETES_VISUALIZAR,
                self::FRETES_CRIAR,
                self::FRETES_EDITAR,
                self::FRETES_IMPORTAR,
                self::FRETES_IMPORTAR_CONFIRMAR,
                self::FRETES_HISTORICO,
                self::FRETES_EXPORTAR_PDF,
            ],
            'Praças' => [
                self::PRACAS_VISUALIZAR,
                self::PRACAS_CRIAR,
                self::PRACAS_EDITAR,
                self::PRACAS_IMPORTAR,
                self::PRACAS_IMPORTAR_CONFIRMAR,
                self::PRACAS_HISTORICO,
                self::PRACAS_EXPORTAR_PDF,
            ],
            'Estoques' => [
                self::ESTOQUES_VISUALIZAR,
                self::ESTOQUES_MOVIMENTAR,
                self::ESTOQUES_IMPORTAR,
                self::ESTOQUES_IMPORTAR_CONFIRMAR,
                self::ESTOQUES_EXPORTAR_PDF,
            ],
            'Movimentações — Compra' => [
                self::MOVIMENTACOES_COMPRAS_VISUALIZAR,
                self::MOVIMENTACOES_COMPRAS_CRIAR,
                self::MOVIMENTACOES_COMPRAS_EDITAR,
                self::MOVIMENTACOES_COMPRAS_CANCELAR_ADMIN,
            ],
            'Movimentações — Transferência' => [
                self::MOVIMENTACOES_TRANSFERENCIAS_VISUALIZAR,
                self::MOVIMENTACOES_TRANSFERENCIAS_CRIAR,
                self::MOVIMENTACOES_TRANSFERENCIAS_RECEBER,
                self::MOVIMENTACOES_TRANSFERENCIAS_REENVIAR,
                self::MOVIMENTACOES_TRANSFERENCIAS_CANCELAR,
                self::MOVIMENTACOES_TRANSFERENCIAS_CANCELAR_ADMIN,
            ],
            'Movimentações — Doação' => [
                self::MOVIMENTACOES_DOACOES_VISUALIZAR,
                self::MOVIMENTACOES_DOACOES_CRIAR,
                self::MOVIMENTACOES_DOACOES_EDITAR,
                self::MOVIMENTACOES_DOACOES_CANCELAR_ADMIN,
            ],
            'Movimentações — Descarte' => [
                self::MOVIMENTACOES_DESCARTES_VISUALIZAR,
                self::MOVIMENTACOES_DESCARTES_CRIAR,
                self::MOVIMENTACOES_DESCARTES_EDITAR,
                self::MOVIMENTACOES_DESCARTES_CANCELAR_ADMIN,
            ],
            'Movimentações — Venda' => [
                self::MOVIMENTACOES_VENDAS_VISUALIZAR,
                self::MOVIMENTACOES_VENDAS_CRIAR,
                self::MOVIMENTACOES_VENDAS_EDITAR,
                self::MOVIMENTACOES_VENDAS_CANCELAR_ADMIN,
            ],
            'Movimentações — Devolução' => [
                self::MOVIMENTACOES_DEVOLUCOES_VISUALIZAR,
                self::MOVIMENTACOES_DEVOLUCOES_CRIAR,
                self::MOVIMENTACOES_DEVOLUCOES_EDITAR,
                self::MOVIMENTACOES_DEVOLUCOES_CANCELAR_ADMIN,
            ],
            'Movimentações — Conversão de Embalagem' => [
                self::MOVIMENTACOES_CONVERSOES_EMBALAGEM_VISUALIZAR,
                self::MOVIMENTACOES_CONVERSOES_EMBALAGEM_CRIAR,
            ],
        ];
    }

    /**
     * Lista plana de todas as permissões do sistema.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::groups()))));
    }
}
