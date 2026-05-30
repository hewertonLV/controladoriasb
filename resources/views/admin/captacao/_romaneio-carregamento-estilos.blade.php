<style>
    .captacao-romaneio-por-rota .captacao-romaneio-nav-tabs {
        flex-wrap: wrap;
        gap: 0.25rem;
        border-bottom: none;
    }

    .captacao-romaneio-por-rota .captacao-romaneio-nav-tabs .nav-link {
        margin-bottom: 0.15rem;
        white-space: normal;
        text-align: center;
        max-width: 14rem;
        line-height: 1.25;
        padding: 0.35rem 0.65rem;
        font-size: 0.8125rem;
    }

    .captacao-romaneio-tabela thead th {
        text-align: center;
        vertical-align: middle;
        padding: 0.3rem 0.35rem;
        font-size: 0.75rem;
        line-height: 1.2;
        white-space: nowrap;
    }

    .captacao-romaneio-tabela tbody tr.captacao-romaneio-linha-item td {
        padding: 0.08rem 0.35rem;
        vertical-align: middle;
        font-size: 0.8125rem;
        line-height: 1.15;
    }

    .captacao-romaneio-tabela tbody tr.captacao-romaneio-linha-total td,
    .captacao-romaneio-tabela tfoot td {
        padding: 0.2rem 0.35rem;
        font-size: 0.8125rem;
        line-height: 1.2;
    }

    .captacao-romaneio-tabela tbody td[rowspan] {
        padding: 0.25rem 0.35rem;
        vertical-align: middle;
    }

    .captacao-romaneio-tabela .captacao-romaneio-total-um-linha {
        line-height: 1.15;
    }

    .captacao-romaneio-tabela .captacao-romaneio-total-um-linha + .captacao-romaneio-total-um-linha {
        margin-top: 0.05rem;
    }

    .captacao-romaneio-tabela .captacao-romaneio-col-fruta {
        text-align: left;
    }

    .captacao-romaneio-tabela .captacao-romaneio-col-loja {
        max-width: 10rem;
    }

    .captacao-romaneio-print-cabecalho {
        display: none;
    }

    @media print {
        @page {
            margin: 8mm 10mm;
            size: A4 portrait;
        }

        html,
        body {
            margin: 0 !important;
            padding: 0 !important;
            height: auto !important;
        }

        body.captacao-romaneio-print-ativo * {
            visibility: hidden;
        }

        body.captacao-romaneio-print-ativo .captacao-romaneio-printando,
        body.captacao-romaneio-print-ativo .captacao-romaneio-printando * {
            visibility: visible;
            color: #000 !important;
        }

        body.captacao-romaneio-print-ativo .captacao-romaneio-printando {
            position: fixed !important;
            inset: 0 auto auto 0 !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        body.captacao-romaneio-print-ativo .captacao-romaneio-printando .captacao-romaneio-imprimir,
        body.captacao-romaneio-print-ativo .captacao-romaneio-printando .captacao-romaneio-nav-tabs,
        body.captacao-romaneio-print-ativo .captacao-romaneio-printando .captacao-romaneio-saida-tela,
        body.captacao-romaneio-print-ativo .captacao-romaneio-printando .captacao-saida-fisica-status {
            display: none !important;
        }

        body.captacao-romaneio-print-ativo .captacao-romaneio-printando .captacao-romaneio-saida-impressao {
            display: block !important;
            color: #000 !important;
        }

        body.captacao-romaneio-print-ativo .captacao-romaneio-printando .tab-pane:not(.active) {
            display: none !important;
        }

        body.captacao-romaneio-print-ativo .captacao-romaneio-printando .tab-pane.active {
            display: block !important;
            opacity: 1 !important;
            position: static !important;
        }

        .captacao-romaneio-print-cabecalho {
            display: block !important;
            border-bottom: none;
            padding: 0 0 0.45rem;
            margin: 0 0 0.35rem;
        }

        .captacao-romaneio-print-cabecalho .captacao-romaneio-print-titulo {
            font-size: 11pt;
            font-weight: 700;
            line-height: 1.25;
            margin: 0;
            letter-spacing: 0;
            color: #0d6efd !important;
        }

        .captacao-romaneio-tabela {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
        }

        .captacao-romaneio-tabela th,
        .captacao-romaneio-tabela td {
            border: 1px solid #333 !important;
            color: #000 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .captacao-romaneio-tabela thead th {
            background: #dee2e6 !important;
            font-weight: 700;
            padding: 0.2rem 0.25rem;
        }

        .captacao-romaneio-tabela tbody tr.captacao-romaneio-loja-par td {
            background: #fff !important;
        }

        .captacao-romaneio-tabela tbody tr.captacao-romaneio-loja-impar td {
            background: #dbeafe !important;
        }

        .captacao-romaneio-tabela tbody tr.captacao-romaneio-linha-total td {
            font-weight: 600;
        }

        .captacao-romaneio-tabela tfoot td {
            background: #cbd5e1 !important;
            font-weight: 700;
        }

        .captacao-romaneio-tabela .captacao-romaneio-col-loja {
            width: 1.6rem;
            max-width: 1.8rem;
            min-width: 1.4rem;
            padding: 0.1rem 0.05rem !important;
            vertical-align: middle;
        }

        .captacao-romaneio-tabela .captacao-romaneio-loja-text {
            display: inline-block;
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            white-space: nowrap;
            font-size: 7.5pt;
            line-height: 1;
            font-weight: 600;
            max-height: 12rem;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .captacao-romaneio-tabela .captacao-romaneio-col-fruta {
            text-align: left !important;
        }
    }
</style>
