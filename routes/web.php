<?php

use App\Http\Controllers\Admin\ClienteController;
use App\Http\Controllers\Admin\ClienteExportacaoController;
use App\Http\Controllers\Admin\ClienteImportacaoController;
use App\Http\Controllers\Admin\EmpresaController;
use App\Http\Controllers\Admin\EmpresaExportacaoController;
use App\Http\Controllers\Admin\EmpresaImportacaoController;
use App\Http\Controllers\Admin\EstadoController;
use App\Http\Controllers\Admin\EstadoImportacaoController;
use App\Http\Controllers\Admin\EstoqueController;
use App\Http\Controllers\Admin\EstoqueExportacaoController;
use App\Http\Controllers\Admin\EstoqueImportacaoController;
use App\Http\Controllers\Admin\FornecedorController;
use App\Http\Controllers\Admin\FornecedorExportacaoController;
use App\Http\Controllers\Admin\FornecedorImportacaoController;
use App\Http\Controllers\Admin\FreteController;
use App\Http\Controllers\Admin\FreteExportacaoController;
use App\Http\Controllers\Admin\FreteImportacaoController;
use App\Http\Controllers\Admin\FrutaController;
use App\Http\Controllers\Admin\FrutaExportacaoController;
use App\Http\Controllers\Admin\FrutaIcmsController;
use App\Http\Controllers\Admin\FrutaIcmsImportacaoController;
use App\Http\Controllers\Admin\FrutaImportacaoController;
use App\Http\Controllers\Admin\GrupoContratoController;
use App\Http\Controllers\Admin\GrupoController;
use App\Http\Controllers\Admin\GrupoExportacaoController;
use App\Http\Controllers\Admin\GrupoImportacaoController;
use App\Http\Controllers\Admin\GrupoPermissaoController;
use App\Http\Controllers\Admin\Movimentacoes\CancelarCompraMovimentacaoController;
use App\Http\Controllers\Admin\Movimentacoes\CancelarDescarteMovimentacaoAdminController;
use App\Http\Controllers\Admin\Movimentacoes\CancelarDoacaoMovimentacaoAdminController;
use App\Http\Controllers\Admin\Movimentacoes\CancelarEntradaEstoqueMovimentacaoAdminController;
use App\Http\Controllers\Admin\Movimentacoes\CancelarTransferenciaMovimentacaoAdminController;
use App\Http\Controllers\Admin\Movimentacoes\CancelarVendaMovimentacaoAdminController;
use App\Http\Controllers\Admin\Movimentacoes\CompraMovimentacaoController;
use App\Http\Controllers\Admin\Movimentacoes\ConversaoEmbalagemMovimentacaoController;
use App\Http\Controllers\Admin\Movimentacoes\DescarteMovimentacaoController;
use App\Http\Controllers\Admin\Movimentacoes\DevolucaoMovimentacaoController;
use App\Http\Controllers\Admin\Movimentacoes\DoacaoMovimentacaoController;
use App\Http\Controllers\Admin\Movimentacoes\EntradaEstoqueMovimentacaoController;
use App\Http\Controllers\Admin\Movimentacoes\TransferenciaImportacaoController;
use App\Http\Controllers\Admin\Movimentacoes\TransferenciaMovimentacaoController;
use App\Http\Controllers\Admin\Movimentacoes\VendaImportacaoController;
use App\Http\Controllers\Admin\Movimentacoes\VendaMovimentacaoController;
use App\Http\Controllers\Admin\PracaController;
use App\Http\Controllers\Admin\PracaExportacaoController;
use App\Http\Controllers\Admin\PracaImportacaoController;
use App\Http\Controllers\Admin\Relatorios\RentabilidadeLojaController;
use App\Http\Controllers\Admin\UnidadeNegocioController;
use App\Http\Controllers\Admin\UnidadeNegocioExportacaoController;
use App\Http\Controllers\Admin\UnidadeNegocioImportacaoController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Admin\VeiculoController;
use App\Http\Controllers\Admin\VeiculoExportacaoController;
use App\Http\Controllers\Admin\VeiculoImportacaoController;
use App\Http\Controllers\Auth\ForcePasswordChangeController;
use App\Http\Controllers\ClientErrorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OlhoDeDeusController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RequestDebugClientReportController;
use App\Http\Controllers\ThemeSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::post('/client-errors', [ClientErrorController::class, 'store'])
    ->name('client-errors.store');

Route::post('/request-debug/client-report', [RequestDebugClientReportController::class, 'store'])
    ->middleware(['auth', 'user.active'])
    ->name('request-debug.client-report');

/*
 * Rotas autenticadas mas SEM o middleware password.changed,
 * pois precisam estar acessíveis durante a troca obrigatória.
 * user.active continua valendo: usuário desativado é deslogado.
 */
Route::middleware(['auth', 'user.active'])->group(function () {
    Route::get('/alterar-senha-obrigatoria', [ForcePasswordChangeController::class, 'show'])
        ->name('password.force.change');

    Route::put('/alterar-senha-obrigatoria', [ForcePasswordChangeController::class, 'update'])
        ->name('password.force.update');
});

/*
 * Rotas autenticadas, sessão ativa e senha já trocada.
 */
Route::middleware(['auth', 'verified', 'user.active', 'password.changed'])->group(function () {
    Route::post('/theme-settings', [ThemeSettingsController::class, 'update'])
        ->name('theme-settings.update');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/dados', [DashboardController::class, 'dados'])
        ->middleware('throttle:'.config('dashboard_financeiro.poll_max_per_minute', 4).',1')
        ->name('dashboard.dados');

    Route::redirect('/olho-de-deus', '/olho-de-fabio', 301);
    Route::redirect('/olho-de-deus/poll', '/olho-de-fabio/poll', 301);
    Route::redirect('/olho-no-gado', '/olho-de-fabio', 301);
    Route::redirect('/olho-no-gado/poll', '/olho-de-fabio/poll', 301);

    Route::middleware(['permission:olho-de-fabio.visualizar'])
        ->prefix('olho-de-fabio')
        ->name('olho-de-fabio.')
        ->group(function (): void {
            Route::get('/', [OlhoDeDeusController::class, 'index'])->name('index');
            Route::get('/poll', [OlhoDeDeusController::class, 'poll'])
                ->middleware('throttle:'.config('olho_de_fabio.poll_max_per_minute', 4).',1')
                ->name('poll');
        });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::prefix('admin')->name('admin.')->group(function () {

        Route::prefix('empresas')->name('empresas.')->group(function () {
            Route::get('/', [EmpresaController::class, 'index'])
                ->middleware('permission:empresas.visualizar')
                ->name('index');

            Route::get('/exportar-pdf', [EmpresaController::class, 'exportarPdf'])
                ->middleware('permission:empresas.exportar-pdf')
                ->name('exportar-pdf');

            Route::post('/exportacoes/pdf/iniciar', [EmpresaExportacaoController::class, 'iniciar'])
                ->middleware('permission:empresas.exportar-pdf')
                ->name('exportacoes.pdf.iniciar');

            Route::get('/exportacoes/{exportacao:uuid}/status', [EmpresaExportacaoController::class, 'status'])
                ->middleware('permission:empresas.exportar-pdf')
                ->name('exportacoes.status');

            Route::get('/exportacoes/{exportacao:uuid}/download', [EmpresaExportacaoController::class, 'download'])
                ->middleware('permission:empresas.exportar-pdf')
                ->name('exportacoes.download');

            Route::get('/importar', [EmpresaImportacaoController::class, 'importar'])
                ->middleware('permission:empresas.importar')
                ->name('importar');

            Route::post('/importar/iniciar', [EmpresaImportacaoController::class, 'iniciar'])
                ->middleware('permission:empresas.importar')
                ->name('importar.iniciar');

            Route::get('/importar/{importacao:uuid}/status', [EmpresaImportacaoController::class, 'status'])
                ->middleware('permission:empresas.importar')
                ->name('importar.status');

            Route::get('/importar/{importacao:uuid}/resultado', [EmpresaImportacaoController::class, 'resultado'])
                ->middleware('permission:empresas.importar')
                ->name('importar.resultado');

            Route::post('/importar/{importacao:uuid}/confirmar', [EmpresaImportacaoController::class, 'confirmar'])
                ->middleware('permission:empresas.importar-confirmar')
                ->name('importar.confirmar');

            Route::get('/{empresa}/historico', [EmpresaController::class, 'historico'])
                ->middleware('permission:empresas.historico')
                ->name('historico');
        });

        Route::prefix('usuarios')->name('usuarios.')->group(function () {
            Route::get('/', [UsuarioController::class, 'index'])
                ->middleware('permission:usuarios.visualizar')
                ->name('index');

            Route::get('/criar', [UsuarioController::class, 'create'])
                ->middleware('permission:usuarios.criar')
                ->name('create');

            Route::post('/', [UsuarioController::class, 'store'])
                ->middleware('permission:usuarios.criar')
                ->name('store');

            Route::get('/{user}/editar', [UsuarioController::class, 'edit'])
                ->middleware('permission:usuarios.editar')
                ->name('edit');

            Route::put('/{user}', [UsuarioController::class, 'update'])
                ->middleware('permission:usuarios.editar')
                ->name('update');

            Route::post('/{user}/resetar-senha', [UsuarioController::class, 'resetPassword'])
                ->middleware('permission:usuarios.resetar-senha')
                ->name('reset-password');

            Route::post('/{user}/desativar', [UsuarioController::class, 'desativar'])
                ->middleware('permission:usuarios.desativar')
                ->name('desativar');

            Route::post('/{user}/reativar', [UsuarioController::class, 'reativar'])
                ->middleware('permission:usuarios.reativar')
                ->name('reativar');
        });

        Route::prefix('unidades-negocio')->name('unidades-negocio.')->group(function () {
            Route::get('/', [UnidadeNegocioController::class, 'index'])
                ->middleware('permission:unidades-negocio.visualizar')
                ->name('index');

            Route::get('/importar', [UnidadeNegocioImportacaoController::class, 'importar'])
                ->middleware('permission:unidades-negocio.importar')
                ->name('importar');

            Route::post('/importar/iniciar', [UnidadeNegocioImportacaoController::class, 'iniciar'])
                ->middleware('permission:unidades-negocio.importar')
                ->name('importar.iniciar');

            Route::get('/importar/{importacao:uuid}/status', [UnidadeNegocioImportacaoController::class, 'status'])
                ->middleware('permission:unidades-negocio.importar')
                ->name('importar.status');

            Route::get('/importar/{importacao:uuid}/resultado', [UnidadeNegocioImportacaoController::class, 'resultado'])
                ->middleware('permission:unidades-negocio.importar')
                ->name('importar.resultado');

            Route::post('/importar/{importacao:uuid}/confirmar', [UnidadeNegocioImportacaoController::class, 'confirmar'])
                ->middleware('permission:unidades-negocio.importar-confirmar')
                ->name('importar.confirmar');

            Route::get('/criar', [UnidadeNegocioController::class, 'create'])
                ->middleware('permission:unidades-negocio.criar')
                ->name('create');

            Route::post('/', [UnidadeNegocioController::class, 'store'])
                ->middleware('permission:unidades-negocio.criar')
                ->name('store');

            Route::get('/{unidadeNegocio}/editar', [UnidadeNegocioController::class, 'edit'])
                ->middleware('permission:unidades-negocio.editar')
                ->name('edit');

            Route::put('/{unidadeNegocio}', [UnidadeNegocioController::class, 'update'])
                ->middleware('permission:unidades-negocio.editar')
                ->name('update');

            Route::post('/{unidadeNegocio}/inativar', [UnidadeNegocioController::class, 'inativar'])
                ->middleware('permission:unidades-negocio.inativar')
                ->name('inativar');

            Route::post('/{unidadeNegocio}/ativar', [UnidadeNegocioController::class, 'ativar'])
                ->middleware('permission:unidades-negocio.ativar')
                ->name('ativar');

            Route::post('/exportacoes/pdf/iniciar', [UnidadeNegocioExportacaoController::class, 'iniciar'])
                ->middleware('permission:unidades-negocio.exportar-pdf')
                ->name('exportacoes.pdf.iniciar');

            Route::get('/exportacoes/{exportacao:uuid}/status', [UnidadeNegocioExportacaoController::class, 'status'])
                ->middleware('permission:unidades-negocio.exportar-pdf')
                ->name('exportacoes.status');

            Route::get('/exportacoes/{exportacao:uuid}/download', [UnidadeNegocioExportacaoController::class, 'download'])
                ->middleware('permission:unidades-negocio.exportar-pdf')
                ->name('exportacoes.download');

            Route::get('/{unidadeNegocio}/historico', [UnidadeNegocioController::class, 'historico'])
                ->middleware('permission:unidades-negocio.historico')
                ->name('historico');
        });

        Route::prefix('fornecedores')->name('fornecedores.')->group(function () {
            Route::get('/', [FornecedorController::class, 'index'])
                ->middleware('permission:fornecedores.visualizar')
                ->name('index');

            Route::post('/exportacoes/pdf/iniciar', [FornecedorExportacaoController::class, 'iniciar'])
                ->middleware('permission:fornecedores.exportar-pdf')
                ->name('exportacoes.pdf.iniciar');

            Route::get('/exportacoes/{exportacao:uuid}/status', [FornecedorExportacaoController::class, 'status'])
                ->middleware('permission:fornecedores.exportar-pdf')
                ->name('exportacoes.status');

            Route::get('/exportacoes/{exportacao:uuid}/download', [FornecedorExportacaoController::class, 'download'])
                ->middleware('permission:fornecedores.exportar-pdf')
                ->name('exportacoes.download');

            Route::get('/importar', [FornecedorImportacaoController::class, 'importar'])
                ->middleware('permission:fornecedores.importar')
                ->name('importar');

            Route::post('/importar/iniciar', [FornecedorImportacaoController::class, 'iniciar'])
                ->middleware('permission:fornecedores.importar')
                ->name('importar.iniciar');

            Route::get('/importar/{importacao:uuid}/status', [FornecedorImportacaoController::class, 'status'])
                ->middleware('permission:fornecedores.importar')
                ->name('importar.status');

            Route::get('/importar/{importacao:uuid}/resultado', [FornecedorImportacaoController::class, 'resultado'])
                ->middleware('permission:fornecedores.importar')
                ->name('importar.resultado');

            Route::post('/importar/{importacao:uuid}/confirmar', [FornecedorImportacaoController::class, 'confirmar'])
                ->middleware('permission:fornecedores.importar-confirmar')
                ->name('importar.confirmar');

            Route::get('/criar', [FornecedorController::class, 'create'])
                ->middleware('permission:fornecedores.criar')
                ->name('create');

            Route::post('/', [FornecedorController::class, 'store'])
                ->middleware('permission:fornecedores.criar')
                ->name('store');

            Route::get('/{fornecedor}/detalhes', [FornecedorController::class, 'show'])
                ->middleware('permission:fornecedores.visualizar')
                ->name('show');

            Route::get('/{fornecedor}/historico', [FornecedorController::class, 'historico'])
                ->middleware('permission:fornecedores.historico')
                ->name('historico');

            Route::get('/{fornecedor}/editar', [FornecedorController::class, 'edit'])
                ->middleware('permission:fornecedores.editar')
                ->name('edit');

            Route::put('/{fornecedor}', [FornecedorController::class, 'update'])
                ->middleware('permission:fornecedores.editar')
                ->name('update');
        });

        Route::prefix('veiculos')->name('veiculos.')->group(function () {
            Route::get('/', [VeiculoController::class, 'index'])
                ->middleware('permission:veiculos.visualizar')
                ->name('index');

            Route::post('/exportacoes/pdf/iniciar', [VeiculoExportacaoController::class, 'iniciar'])
                ->middleware('permission:veiculos.exportar-pdf')
                ->name('exportacoes.pdf.iniciar');

            Route::get('/exportacoes/{exportacao:uuid}/status', [VeiculoExportacaoController::class, 'status'])
                ->middleware('permission:veiculos.exportar-pdf')
                ->name('exportacoes.status');

            Route::get('/exportacoes/{exportacao:uuid}/download', [VeiculoExportacaoController::class, 'download'])
                ->middleware('permission:veiculos.exportar-pdf')
                ->name('exportacoes.download');

            Route::get('/importar', [VeiculoImportacaoController::class, 'importar'])
                ->middleware('permission:veiculos.importar')
                ->name('importar');

            Route::post('/importar/iniciar', [VeiculoImportacaoController::class, 'iniciar'])
                ->middleware('permission:veiculos.importar')
                ->name('importar.iniciar');

            Route::get('/importar/{importacao:uuid}/status', [VeiculoImportacaoController::class, 'status'])
                ->middleware('permission:veiculos.importar')
                ->name('importar.status');

            Route::get('/importar/{importacao:uuid}/resultado', [VeiculoImportacaoController::class, 'resultado'])
                ->middleware('permission:veiculos.importar')
                ->name('importar.resultado');

            Route::post('/importar/{importacao:uuid}/confirmar', [VeiculoImportacaoController::class, 'confirmar'])
                ->middleware('permission:veiculos.importar-confirmar')
                ->name('importar.confirmar');

            Route::get('/criar', [VeiculoController::class, 'create'])
                ->middleware('permission:veiculos.criar')
                ->name('create');

            Route::post('/', [VeiculoController::class, 'store'])
                ->middleware('permission:veiculos.criar')
                ->name('store');

            Route::get('/{veiculo}/historico', [VeiculoController::class, 'historico'])
                ->middleware('permission:veiculos.historico')
                ->name('historico');

            Route::get('/{veiculo}/editar', [VeiculoController::class, 'edit'])
                ->middleware('permission:veiculos.editar')
                ->name('edit');

            Route::put('/{veiculo}', [VeiculoController::class, 'update'])
                ->middleware('permission:veiculos.editar')
                ->name('update');

            Route::post('/{veiculo}/inativar', [VeiculoController::class, 'inativar'])
                ->middleware('permission:veiculos.inativar')
                ->name('inativar');

            Route::post('/{veiculo}/reativar', [VeiculoController::class, 'reativar'])
                ->middleware('permission:veiculos.reativar')
                ->name('reativar');
        });

        Route::prefix('clientes')->name('clientes.')->group(function () {
            Route::get('/', [ClienteController::class, 'index'])
                ->middleware('permission:clientes.visualizar')
                ->name('index');

            Route::post('/exportacoes/pdf/iniciar', [ClienteExportacaoController::class, 'iniciar'])
                ->middleware('permission:clientes.exportar-pdf')
                ->name('exportacoes.pdf.iniciar');

            Route::get('/exportacoes/{exportacao:uuid}/status', [ClienteExportacaoController::class, 'status'])
                ->middleware('permission:clientes.exportar-pdf')
                ->name('exportacoes.status');

            Route::get('/exportacoes/{exportacao:uuid}/download', [ClienteExportacaoController::class, 'download'])
                ->middleware('permission:clientes.exportar-pdf')
                ->name('exportacoes.download');

            Route::get('/importar', [ClienteImportacaoController::class, 'importar'])
                ->middleware('permission:clientes.importar')
                ->name('importar');

            Route::post('/importar/iniciar', [ClienteImportacaoController::class, 'iniciar'])
                ->middleware('permission:clientes.importar')
                ->name('importar.iniciar');

            Route::get('/importar/{importacao:uuid}/status', [ClienteImportacaoController::class, 'status'])
                ->middleware('permission:clientes.importar')
                ->name('importar.status');

            Route::get('/importar/{importacao:uuid}/resultado', [ClienteImportacaoController::class, 'resultado'])
                ->middleware('permission:clientes.importar')
                ->name('importar.resultado');

            Route::post('/importar/{importacao:uuid}/confirmar', [ClienteImportacaoController::class, 'confirmar'])
                ->middleware('permission:clientes.importar-confirmar')
                ->name('importar.confirmar');

            Route::get('/criar', [ClienteController::class, 'create'])
                ->middleware('permission:clientes.criar')
                ->name('create');

            Route::post('/', [ClienteController::class, 'store'])
                ->middleware('permission:clientes.criar')
                ->name('store');

            Route::get('/{cliente}/historico', [ClienteController::class, 'historico'])
                ->middleware('permission:clientes.historico')
                ->name('historico');

            Route::get('/{cliente}/editar', [ClienteController::class, 'edit'])
                ->middleware('permission:clientes.editar')
                ->name('edit');

            Route::put('/{cliente}', [ClienteController::class, 'update'])
                ->middleware('permission:clientes.editar')
                ->name('update');
        });

        Route::prefix('grupos')->name('grupos.')->group(function () {
            Route::get('/', [GrupoController::class, 'index'])
                ->middleware('permission:grupos.visualizar')
                ->name('index');

            Route::post('/exportacoes/pdf/iniciar', [GrupoExportacaoController::class, 'iniciar'])
                ->middleware('permission:grupos.exportar-pdf')
                ->name('exportacoes.pdf.iniciar');

            Route::get('/exportacoes/{exportacao:uuid}/status', [GrupoExportacaoController::class, 'status'])
                ->middleware('permission:grupos.exportar-pdf')
                ->name('exportacoes.status');

            Route::get('/exportacoes/{exportacao:uuid}/download', [GrupoExportacaoController::class, 'download'])
                ->middleware('permission:grupos.exportar-pdf')
                ->name('exportacoes.download');

            Route::get('/importar', [GrupoImportacaoController::class, 'importar'])
                ->middleware('permission:grupos.importar')
                ->name('importar');

            Route::post('/importar/iniciar', [GrupoImportacaoController::class, 'iniciar'])
                ->middleware('permission:grupos.importar')
                ->name('importar.iniciar');

            Route::get('/importar/{importacao:uuid}/status', [GrupoImportacaoController::class, 'status'])
                ->middleware('permission:grupos.importar')
                ->name('importar.status');

            Route::get('/importar/{importacao:uuid}/resultado', [GrupoImportacaoController::class, 'resultado'])
                ->middleware('permission:grupos.importar')
                ->name('importar.resultado');

            Route::post('/importar/{importacao:uuid}/confirmar', [GrupoImportacaoController::class, 'confirmar'])
                ->middleware('permission:grupos.importar-confirmar')
                ->name('importar.confirmar');

            Route::get('/criar', [GrupoController::class, 'create'])
                ->middleware('permission:grupos.criar')
                ->name('create');

            Route::post('/', [GrupoController::class, 'store'])
                ->middleware('permission:grupos.criar')
                ->name('store');

            Route::get('/{grupo}/historico', [GrupoController::class, 'historico'])
                ->middleware('permission:grupos.historico')
                ->name('historico');

            Route::get('/{grupo}/editar', [GrupoController::class, 'edit'])
                ->middleware('permission:grupos.editar')
                ->name('edit');

            Route::put('/{grupo}', [GrupoController::class, 'update'])
                ->middleware('permission:grupos.editar')
                ->name('update');
        });

        Route::prefix('estados')->name('estados.')->group(function () {
            Route::get('/', [EstadoController::class, 'index'])
                ->middleware('permission:estados.visualizar')
                ->name('index');

            Route::get('/importar', [EstadoImportacaoController::class, 'importar'])
                ->middleware('permission:estados.importar')
                ->name('importar');

            Route::post('/importar/iniciar', [EstadoImportacaoController::class, 'iniciar'])
                ->middleware('permission:estados.importar')
                ->name('importar.iniciar');

            Route::get('/importar/{importacao:uuid}/status', [EstadoImportacaoController::class, 'status'])
                ->middleware('permission:estados.importar')
                ->name('importar.status');

            Route::get('/importar/{importacao:uuid}/resultado', [EstadoImportacaoController::class, 'resultado'])
                ->middleware('permission:estados.importar')
                ->name('importar.resultado');

            Route::post('/importar/{importacao:uuid}/confirmar', [EstadoImportacaoController::class, 'confirmar'])
                ->middleware('permission:estados.importar-confirmar')
                ->name('importar.confirmar');

            Route::get('/criar', [EstadoController::class, 'create'])
                ->middleware('permission:estados.criar')
                ->name('create');

            Route::post('/', [EstadoController::class, 'store'])
                ->middleware('permission:estados.criar')
                ->name('store');

            Route::get('/{estado}/editar', [EstadoController::class, 'edit'])
                ->middleware('permission:estados.editar')
                ->name('edit');

            Route::put('/{estado}', [EstadoController::class, 'update'])
                ->middleware('permission:estados.editar')
                ->name('update');

            Route::post('/{estado}/inativar', [EstadoController::class, 'inativar'])
                ->middleware('permission:estados.inativar')
                ->name('inativar');

            Route::post('/{estado}/reativar', [EstadoController::class, 'reativar'])
                ->middleware('permission:estados.reativar')
                ->name('reativar');
        });

        Route::prefix('grupos-contrato')->name('grupos-contrato.')->group(function () {
            Route::get('/', [GrupoContratoController::class, 'index'])
                ->middleware('permission:grupos-contrato.visualizar')
                ->name('index');

            Route::get('/criar', [GrupoContratoController::class, 'create'])
                ->middleware('permission:grupos-contrato.criar')
                ->name('create');

            Route::post('/', [GrupoContratoController::class, 'store'])
                ->middleware('permission:grupos-contrato.criar')
                ->name('store');

            Route::get('/{grupoContrato}', [GrupoContratoController::class, 'show'])
                ->middleware('permission:grupos-contrato.visualizar')
                ->name('show');

            Route::get('/{grupoContrato}/editar', [GrupoContratoController::class, 'edit'])
                ->middleware('permission:grupos-contrato.editar')
                ->name('edit');

            Route::put('/{grupoContrato}', [GrupoContratoController::class, 'update'])
                ->middleware('permission:grupos-contrato.editar')
                ->name('update');

            Route::post('/{grupoContrato}/membros', [GrupoContratoController::class, 'storeMembro'])
                ->middleware('permission:grupos-contrato.membros')
                ->name('membros.store');

            Route::post('/{grupoContrato}/descontos', [GrupoContratoController::class, 'storeDesconto'])
                ->middleware('permission:grupos-contrato.descontos')
                ->name('descontos.store');

            Route::get('/{grupoContrato}/historico', [GrupoContratoController::class, 'historico'])
                ->middleware('permission:grupos-contrato.historico')
                ->name('historico');
        });

        Route::prefix('frutas')->name('frutas.')->group(function () {
            Route::get('/', [FrutaController::class, 'index'])
                ->middleware('permission:frutas.visualizar')
                ->name('index');

            Route::post('/exportacoes/pdf/iniciar', [FrutaExportacaoController::class, 'iniciar'])
                ->middleware('permission:frutas.exportar-pdf')
                ->name('exportacoes.pdf.iniciar');

            Route::get('/exportacoes/{exportacao:uuid}/status', [FrutaExportacaoController::class, 'status'])
                ->middleware('permission:frutas.exportar-pdf')
                ->name('exportacoes.status');

            Route::get('/exportacoes/{exportacao:uuid}/download', [FrutaExportacaoController::class, 'download'])
                ->middleware('permission:frutas.exportar-pdf')
                ->name('exportacoes.download');

            Route::get('/importar', [FrutaImportacaoController::class, 'importar'])
                ->middleware('permission:frutas.importar')
                ->name('importar');

            Route::post('/importar/iniciar', [FrutaImportacaoController::class, 'iniciar'])
                ->middleware('permission:frutas.importar')
                ->name('importar.iniciar');

            Route::get('/importar/{importacao:uuid}/status', [FrutaImportacaoController::class, 'status'])
                ->middleware('permission:frutas.importar')
                ->name('importar.status');

            Route::get('/importar/{importacao:uuid}/resultado', [FrutaImportacaoController::class, 'resultado'])
                ->middleware('permission:frutas.importar')
                ->name('importar.resultado');

            Route::post('/importar/{importacao:uuid}/confirmar', [FrutaImportacaoController::class, 'confirmar'])
                ->middleware('permission:frutas.importar-confirmar')
                ->name('importar.confirmar');

            Route::get('/icms', [FrutaIcmsController::class, 'index'])
                ->middleware('permission:frutas.icms.visualizar')
                ->name('icms.index');

            Route::get('/icms/criar', [FrutaIcmsController::class, 'create'])
                ->middleware('permission:frutas.icms.criar')
                ->name('icms.create');

            Route::post('/icms', [FrutaIcmsController::class, 'store'])
                ->middleware('permission:frutas.icms.criar')
                ->name('icms.store');

            Route::get('/icms/{fruta}/estados/{estado}/editar', [FrutaIcmsController::class, 'edit'])
                ->middleware('permission:frutas.icms.editar')
                ->name('icms.edit');

            Route::put('/icms/{fruta}/estados/{estado}', [FrutaIcmsController::class, 'update'])
                ->middleware('permission:frutas.icms.editar')
                ->name('icms.update');

            Route::get('/icms/importar', [FrutaIcmsImportacaoController::class, 'importar'])
                ->middleware('permission:frutas.icms.importar')
                ->name('icms.importar');

            Route::post('/icms/importar/iniciar', [FrutaIcmsImportacaoController::class, 'iniciar'])
                ->middleware('permission:frutas.icms.importar')
                ->name('icms.importar.iniciar');

            Route::get('/icms/importar/{importacao:uuid}/status', [FrutaIcmsImportacaoController::class, 'status'])
                ->middleware('permission:frutas.icms.importar')
                ->name('icms.importar.status');

            Route::get('/icms/importar/{importacao:uuid}/resultado', [FrutaIcmsImportacaoController::class, 'resultado'])
                ->middleware('permission:frutas.icms.importar')
                ->name('icms.importar.resultado');

            Route::post('/icms/importar/{importacao:uuid}/confirmar', [FrutaIcmsImportacaoController::class, 'confirmar'])
                ->middleware('permission:frutas.icms.importar-confirmar')
                ->name('icms.importar.confirmar');

            Route::get('/criar', [FrutaController::class, 'create'])
                ->middleware('permission:frutas.criar')
                ->name('create');

            Route::post('/', [FrutaController::class, 'store'])
                ->middleware('permission:frutas.criar')
                ->name('store');

            Route::get('/{fruta}/historico', [FrutaController::class, 'historico'])
                ->middleware('permission:frutas.historico')
                ->name('historico');

            Route::get('/{fruta}/editar', [FrutaController::class, 'edit'])
                ->middleware('permission:frutas.editar')
                ->name('edit');

            Route::put('/{fruta}', [FrutaController::class, 'update'])
                ->middleware('permission:frutas.editar')
                ->name('update');
        });

        Route::prefix('fretes')->name('fretes.')->group(function () {
            Route::get('/', [FreteController::class, 'index'])
                ->middleware('permission:fretes.visualizar')
                ->name('index');

            Route::post('/exportacoes/pdf/iniciar', [FreteExportacaoController::class, 'iniciar'])
                ->middleware('permission:fretes.exportar-pdf')
                ->name('exportacoes.pdf.iniciar');

            Route::get('/exportacoes/{exportacao:uuid}/status', [FreteExportacaoController::class, 'status'])
                ->middleware('permission:fretes.exportar-pdf')
                ->name('exportacoes.status');

            Route::get('/exportacoes/{exportacao:uuid}/download', [FreteExportacaoController::class, 'download'])
                ->middleware('permission:fretes.exportar-pdf')
                ->name('exportacoes.download');

            Route::get('/importar', [FreteImportacaoController::class, 'importar'])
                ->middleware('permission:fretes.importar')
                ->name('importar');

            Route::post('/importar/iniciar', [FreteImportacaoController::class, 'iniciar'])
                ->middleware('permission:fretes.importar')
                ->name('importar.iniciar');

            Route::get('/importar/{importacao:uuid}/status', [FreteImportacaoController::class, 'status'])
                ->middleware('permission:fretes.importar')
                ->name('importar.status');

            Route::get('/importar/{importacao:uuid}/resultado', [FreteImportacaoController::class, 'resultado'])
                ->middleware('permission:fretes.importar')
                ->name('importar.resultado');

            Route::post('/importar/{importacao:uuid}/confirmar', [FreteImportacaoController::class, 'confirmar'])
                ->middleware('permission:fretes.importar-confirmar')
                ->name('importar.confirmar');

            Route::get('/calendario', [FreteController::class, 'calendario'])
                ->middleware('permission:fretes.visualizar')
                ->name('calendario');

            Route::get('/calendario/eventos', [FreteController::class, 'calendarioEventos'])
                ->middleware('permission:fretes.visualizar')
                ->name('calendario.eventos');

            Route::get('/criar', [FreteController::class, 'create'])
                ->middleware('permission:fretes.criar')
                ->name('create');

            Route::post('/', [FreteController::class, 'store'])
                ->middleware('permission:fretes.criar')
                ->name('store');

            Route::get('/{frete}/historico', [FreteController::class, 'historico'])
                ->middleware('permission:fretes.historico')
                ->name('historico');

            Route::get('/{frete}/editar', [FreteController::class, 'edit'])
                ->middleware('permission:fretes.editar')
                ->name('edit');

            Route::put('/{frete}', [FreteController::class, 'update'])
                ->middleware('permission:fretes.editar')
                ->name('update');
        });

        Route::prefix('pracas')->name('pracas.')->group(function () {
            Route::get('/', [PracaController::class, 'index'])
                ->middleware('permission:pracas.visualizar')
                ->name('index');

            Route::post('/exportacoes/pdf/iniciar', [PracaExportacaoController::class, 'iniciar'])
                ->middleware('permission:pracas.exportar-pdf')
                ->name('exportacoes.pdf.iniciar');

            Route::get('/exportacoes/{exportacao:uuid}/status', [PracaExportacaoController::class, 'status'])
                ->middleware('permission:pracas.exportar-pdf')
                ->name('exportacoes.status');

            Route::get('/exportacoes/{exportacao:uuid}/download', [PracaExportacaoController::class, 'download'])
                ->middleware('permission:pracas.exportar-pdf')
                ->name('exportacoes.download');

            Route::get('/importar', [PracaImportacaoController::class, 'importar'])
                ->middleware('permission:pracas.importar')
                ->name('importar');

            Route::post('/importar/iniciar', [PracaImportacaoController::class, 'iniciar'])
                ->middleware('permission:pracas.importar')
                ->name('importar.iniciar');

            Route::get('/importar/{importacao:uuid}/status', [PracaImportacaoController::class, 'status'])
                ->middleware('permission:pracas.importar')
                ->name('importar.status');

            Route::get('/importar/{importacao:uuid}/resultado', [PracaImportacaoController::class, 'resultado'])
                ->middleware('permission:pracas.importar')
                ->name('importar.resultado');

            Route::post('/importar/{importacao:uuid}/confirmar', [PracaImportacaoController::class, 'confirmar'])
                ->middleware('permission:pracas.importar-confirmar')
                ->name('importar.confirmar');

            Route::get('/criar', [PracaController::class, 'create'])
                ->middleware('permission:pracas.criar')
                ->name('create');

            Route::post('/', [PracaController::class, 'store'])
                ->middleware('permission:pracas.criar')
                ->name('store');

            Route::get('/{praca}/historico', [PracaController::class, 'historico'])
                ->middleware('permission:pracas.historico')
                ->name('historico');

            Route::get('/{praca}/editar', [PracaController::class, 'edit'])
                ->middleware('permission:pracas.editar')
                ->name('edit');

            Route::put('/{praca}', [PracaController::class, 'update'])
                ->middleware('permission:pracas.editar')
                ->name('update');
        });

        Route::prefix('estoques')->name('estoques.')->group(function () {
            Route::get('/', [EstoqueController::class, 'index'])
                ->middleware('permission:estoques.visualizar')
                ->name('index');

            Route::post('/exportacoes/pdf/iniciar', [EstoqueExportacaoController::class, 'iniciar'])
                ->middleware('permission:estoques.exportar-pdf')
                ->name('exportacoes.pdf.iniciar');

            Route::get('/exportacoes/{exportacao:uuid}/status', [EstoqueExportacaoController::class, 'status'])
                ->middleware('permission:estoques.exportar-pdf')
                ->name('exportacoes.status');

            Route::get('/exportacoes/{exportacao:uuid}/download', [EstoqueExportacaoController::class, 'download'])
                ->middleware('permission:estoques.exportar-pdf')
                ->name('exportacoes.download');

            Route::get('/importar', [EstoqueImportacaoController::class, 'importar'])
                ->middleware('permission:estoques.importar')
                ->name('importar');

            Route::post('/importar/iniciar', [EstoqueImportacaoController::class, 'iniciar'])
                ->middleware('permission:estoques.importar')
                ->name('importar.iniciar');

            Route::get('/importar/{importacao:uuid}/status', [EstoqueImportacaoController::class, 'status'])
                ->middleware('permission:estoques.importar')
                ->name('importar.status');

            Route::get('/importar/{importacao:uuid}/resultado', [EstoqueImportacaoController::class, 'resultado'])
                ->middleware('permission:estoques.importar')
                ->name('importar.resultado');

            Route::post('/importar/{importacao:uuid}/confirmar', [EstoqueImportacaoController::class, 'confirmar'])
                ->middleware('permission:estoques.importar-confirmar')
                ->name('importar.confirmar');

            Route::get('/movimentar', [EstoqueController::class, 'movimentarForm'])
                ->middleware('permission:estoques.movimentar')
                ->name('movimentar');

            Route::post('/movimentar', [EstoqueController::class, 'movimentarStore'])
                ->middleware('permission:estoques.movimentar')
                ->name('movimentar.store');

            Route::get('/unidades/{unidadeNegocio}', [EstoqueController::class, 'unidade'])
                ->middleware('permission:estoques.visualizar')
                ->name('unidade');

            Route::get('/{estoque}', [EstoqueController::class, 'show'])
                ->middleware('permission:estoques.visualizar')
                ->name('show');
        });

        Route::prefix('movimentacoes/compras')->name('movimentacoes.compras.')->group(function () {
            Route::get('/', [CompraMovimentacaoController::class, 'index'])
                ->middleware('permission:movimentacoes.compras.visualizar')
                ->name('index');

            Route::get('/criar', [CompraMovimentacaoController::class, 'create'])
                ->middleware('permission:movimentacoes.compras.criar')
                ->name('create');

            Route::post('/', [CompraMovimentacaoController::class, 'store'])
                ->middleware('permission:movimentacoes.compras.criar')
                ->name('store');

            Route::get('/{movimentacao}', [CompraMovimentacaoController::class, 'show'])
                ->middleware('permission:movimentacoes.compras.visualizar')
                ->name('show');

            Route::get('/{movimentacao}/editar', [CompraMovimentacaoController::class, 'edit'])
                ->middleware('permission:movimentacoes.compras.editar')
                ->name('edit');

            Route::put('/{movimentacao}', [CompraMovimentacaoController::class, 'update'])
                ->middleware('permission:movimentacoes.compras.editar')
                ->name('update');

            Route::post('/{movimentacao}/cancelar-admin', CancelarCompraMovimentacaoController::class)
                ->middleware('role_or_permission:Administrador|movimentacoes.compras.cancelar-admin')
                ->name('cancelar-admin');
        });

        Route::prefix('movimentacoes/transferencias')->name('movimentacoes.transferencias.')->group(function () {
            Route::get('/', [TransferenciaMovimentacaoController::class, 'index'])
                ->middleware('permission:movimentacoes.transferencias.visualizar')
                ->name('index');

            Route::get('/criar', [TransferenciaMovimentacaoController::class, 'create'])
                ->middleware('permission:movimentacoes.transferencias.criar')
                ->name('create');

            Route::post('/', [TransferenciaMovimentacaoController::class, 'store'])
                ->middleware('permission:movimentacoes.transferencias.criar')
                ->name('store');

            Route::get('/importar', [TransferenciaImportacaoController::class, 'importar'])
                ->middleware('permission:movimentacoes.transferencias.importar')
                ->name('importar');

            Route::post('/importar/iniciar', [TransferenciaImportacaoController::class, 'iniciar'])
                ->middleware('permission:movimentacoes.transferencias.importar')
                ->name('importar.iniciar');

            Route::get('/importar/{importacao:uuid}/status', [TransferenciaImportacaoController::class, 'status'])
                ->middleware('permission:movimentacoes.transferencias.importar')
                ->name('importar.status');

            Route::get('/importar/{importacao:uuid}/resultado', [TransferenciaImportacaoController::class, 'resultado'])
                ->middleware('permission:movimentacoes.transferencias.importar')
                ->name('importar.resultado');

            Route::post('/importar/{importacao:uuid}/confirmar', [TransferenciaImportacaoController::class, 'confirmar'])
                ->middleware('permission:movimentacoes.transferencias.importar-confirmar')
                ->name('importar.confirmar');

            Route::get('/{transferenciaOrigem}', [TransferenciaMovimentacaoController::class, 'show'])
                ->middleware('permission:movimentacoes.transferencias.visualizar')
                ->name('show');

            Route::post('/{transferenciaOrigem}/vincular-frete', [TransferenciaMovimentacaoController::class, 'vincularFrete'])
                ->middleware('permission:movimentacoes.transferencias.editar')
                ->name('vincular-frete');

            Route::post('/{transferenciaOrigem}/cancelar', [TransferenciaMovimentacaoController::class, 'cancelar'])
                ->middleware('permission:movimentacoes.transferencias.cancelar')
                ->name('cancelar');

            Route::post('/{transferenciaOrigem}/cancelar-admin', CancelarTransferenciaMovimentacaoAdminController::class)
                ->middleware('role_or_permission:Administrador|movimentacoes.transferencias.cancelar-admin')
                ->name('cancelar-admin');
        });

        Route::prefix('movimentacoes/doacoes')->name('movimentacoes.doacoes.')->group(function () {
            Route::get('/', [DoacaoMovimentacaoController::class, 'index'])
                ->middleware('permission:movimentacoes.doacoes.visualizar')
                ->name('index');

            Route::get('/criar', [DoacaoMovimentacaoController::class, 'create'])
                ->middleware('permission:movimentacoes.doacoes.criar')
                ->name('create');

            Route::post('/', [DoacaoMovimentacaoController::class, 'store'])
                ->middleware('permission:movimentacoes.doacoes.criar')
                ->name('store');

            Route::get('/{movimentacaoDoacao}', [DoacaoMovimentacaoController::class, 'show'])
                ->middleware('permission:movimentacoes.doacoes.visualizar')
                ->name('show');

            Route::get('/{movimentacaoDoacao}/editar', [DoacaoMovimentacaoController::class, 'edit'])
                ->middleware('permission:movimentacoes.doacoes.editar')
                ->name('edit');

            Route::put('/{movimentacaoDoacao}', [DoacaoMovimentacaoController::class, 'update'])
                ->middleware('permission:movimentacoes.doacoes.editar')
                ->name('update');

            Route::post('/{movimentacaoDoacao}/cancelar-admin', CancelarDoacaoMovimentacaoAdminController::class)
                ->middleware('role_or_permission:Administrador|movimentacoes.doacoes.cancelar-admin')
                ->name('cancelar-admin');
        });

        Route::prefix('movimentacoes/entradas-estoque')->name('movimentacoes.entradas-estoque.')->group(function () {
            Route::get('/', [EntradaEstoqueMovimentacaoController::class, 'index'])
                ->middleware('permission:movimentacoes.entradas-estoque.visualizar')
                ->name('index');

            Route::get('/criar', [EntradaEstoqueMovimentacaoController::class, 'create'])
                ->middleware('permission:movimentacoes.entradas-estoque.criar')
                ->name('create');

            Route::post('/', [EntradaEstoqueMovimentacaoController::class, 'store'])
                ->middleware('permission:movimentacoes.entradas-estoque.criar')
                ->name('store');

            Route::get('/{movimentacaoEntradaEstoque}', [EntradaEstoqueMovimentacaoController::class, 'show'])
                ->middleware('permission:movimentacoes.entradas-estoque.visualizar')
                ->name('show');

            Route::post('/{movimentacaoEntradaEstoque}/cancelar-admin', CancelarEntradaEstoqueMovimentacaoAdminController::class)
                ->middleware('role_or_permission:Administrador|movimentacoes.entradas-estoque.cancelar-admin')
                ->name('cancelar-admin');
        });

        Route::prefix('movimentacoes/descartes')->name('movimentacoes.descartes.')->group(function () {
            Route::get('/', [DescarteMovimentacaoController::class, 'index'])
                ->middleware('permission:movimentacoes.descartes.visualizar')
                ->name('index');

            Route::get('/criar', [DescarteMovimentacaoController::class, 'create'])
                ->middleware('permission:movimentacoes.descartes.criar')
                ->name('create');

            Route::post('/', [DescarteMovimentacaoController::class, 'store'])
                ->middleware('permission:movimentacoes.descartes.criar')
                ->name('store');

            Route::get('/{movimentacaoDescarte}', [DescarteMovimentacaoController::class, 'show'])
                ->middleware('permission:movimentacoes.descartes.visualizar')
                ->name('show');

            Route::get('/{movimentacaoDescarte}/editar', [DescarteMovimentacaoController::class, 'edit'])
                ->middleware('permission:movimentacoes.descartes.editar')
                ->name('edit');

            Route::put('/{movimentacaoDescarte}', [DescarteMovimentacaoController::class, 'update'])
                ->middleware('permission:movimentacoes.descartes.editar')
                ->name('update');

            Route::post('/{movimentacaoDescarte}/cancelar-admin', CancelarDescarteMovimentacaoAdminController::class)
                ->middleware('role_or_permission:Administrador|movimentacoes.descartes.cancelar-admin')
                ->name('cancelar-admin');
        });

        Route::prefix('movimentacoes/vendas')->name('movimentacoes.vendas.')->group(function () {
            Route::get('/', [VendaMovimentacaoController::class, 'index'])
                ->middleware('permission:movimentacoes.vendas.visualizar')
                ->name('index');

            Route::get('/importar', [VendaImportacaoController::class, 'importar'])
                ->middleware('permission:movimentacoes.vendas.importar')
                ->name('importar');

            Route::post('/importar/iniciar', [VendaImportacaoController::class, 'iniciar'])
                ->middleware('permission:movimentacoes.vendas.importar')
                ->name('importar.iniciar');

            Route::get('/importar/{importacao:uuid}/status', [VendaImportacaoController::class, 'status'])
                ->middleware('permission:movimentacoes.vendas.importar')
                ->name('importar.status');

            Route::get('/importar/{importacao:uuid}/resultado', [VendaImportacaoController::class, 'resultado'])
                ->middleware('permission:movimentacoes.vendas.importar')
                ->name('importar.resultado');

            Route::post('/importar/{importacao:uuid}/confirmar', [VendaImportacaoController::class, 'confirmar'])
                ->middleware('permission:movimentacoes.vendas.importar-confirmar')
                ->name('importar.confirmar');

            Route::get('/criar', [VendaMovimentacaoController::class, 'create'])
                ->middleware('permission:movimentacoes.vendas.criar')
                ->name('create');

            Route::post('/', [VendaMovimentacaoController::class, 'store'])
                ->middleware('permission:movimentacoes.vendas.criar')
                ->name('store');

            Route::get('/{movimentacaoVenda}', [VendaMovimentacaoController::class, 'show'])
                ->middleware('permission:movimentacoes.vendas.visualizar')
                ->name('show');

            Route::get('/{movimentacaoVenda}/editar', [VendaMovimentacaoController::class, 'edit'])
                ->middleware('permission:movimentacoes.vendas.editar')
                ->name('edit');

            Route::put('/{movimentacaoVenda}', [VendaMovimentacaoController::class, 'update'])
                ->middleware('permission:movimentacoes.vendas.editar')
                ->name('update');

            Route::post('/{movimentacaoVenda}/cancelar-item-admin', [CancelarVendaMovimentacaoAdminController::class, 'item'])
                ->middleware('role_or_permission:Administrador|movimentacoes.vendas.cancelar-admin')
                ->name('cancelar-item-admin');

            Route::post('/{movimentacaoVenda}/cancelar-admin', CancelarVendaMovimentacaoAdminController::class)
                ->middleware('role_or_permission:Administrador|movimentacoes.vendas.cancelar-admin')
                ->name('cancelar-admin');
        });

        Route::prefix('movimentacoes/devolucoes')->name('movimentacoes.devolucoes.')->group(function () {
            Route::get('/', [DevolucaoMovimentacaoController::class, 'index'])
                ->middleware('permission:movimentacoes.devolucoes.visualizar')
                ->name('index');

            Route::get('/criar', [DevolucaoMovimentacaoController::class, 'create'])
                ->middleware('permission:movimentacoes.devolucoes.criar')
                ->name('create');

            Route::post('/', [DevolucaoMovimentacaoController::class, 'store'])
                ->middleware('permission:movimentacoes.devolucoes.criar')
                ->name('store');

            Route::get('/{movimentacaoDevolucao}', [DevolucaoMovimentacaoController::class, 'show'])
                ->middleware('permission:movimentacoes.devolucoes.visualizar')
                ->name('show');

            Route::get('/{movimentacaoDevolucao}/editar', [DevolucaoMovimentacaoController::class, 'edit'])
                ->middleware('permission:movimentacoes.devolucoes.editar')
                ->name('edit');

            Route::put('/{movimentacaoDevolucao}', [DevolucaoMovimentacaoController::class, 'update'])
                ->middleware('permission:movimentacoes.devolucoes.editar')
                ->name('update');

            Route::post('/{movimentacaoDevolucao}/cancelar-admin', [DevolucaoMovimentacaoController::class, 'cancelarAdmin'])
                ->middleware('role_or_permission:Administrador|movimentacoes.devolucoes.cancelar-admin')
                ->name('cancelar-admin');
        });

        Route::prefix('movimentacoes/conversoes-embalagem')->name('movimentacoes.conversoes-embalagem.')->group(function () {
            Route::get('/', [ConversaoEmbalagemMovimentacaoController::class, 'index'])
                ->middleware('permission:movimentacoes.conversoes-embalagem.visualizar')
                ->name('index');

            Route::get('/criar', [ConversaoEmbalagemMovimentacaoController::class, 'create'])
                ->middleware('permission:movimentacoes.conversoes-embalagem.criar')
                ->name('create');

            Route::post('/', [ConversaoEmbalagemMovimentacaoController::class, 'store'])
                ->middleware('permission:movimentacoes.conversoes-embalagem.criar')
                ->name('store');

            Route::get('/{movimentacaoConversao}', [ConversaoEmbalagemMovimentacaoController::class, 'show'])
                ->middleware('permission:movimentacoes.conversoes-embalagem.visualizar')
                ->name('show');
        });

        Route::prefix('relatorios')->name('relatorios.')->group(function () {
            Route::get('/rentabilidade-loja', RentabilidadeLojaController::class)
                ->middleware('permission:relatorios.rentabilidade-loja.visualizar')
                ->name('rentabilidade-loja.index');
        });

        Route::prefix('grupos-permissoes')->name('grupos-permissoes.')->group(function () {
            Route::get('/', [GrupoPermissaoController::class, 'index'])
                ->middleware('permission:grupos-permissoes.visualizar')
                ->name('index');

            Route::get('/criar', [GrupoPermissaoController::class, 'create'])
                ->middleware('permission:grupos-permissoes.criar')
                ->name('create');

            Route::post('/', [GrupoPermissaoController::class, 'store'])
                ->middleware('permission:grupos-permissoes.criar')
                ->name('store');

            Route::get('/{role}/editar', [GrupoPermissaoController::class, 'edit'])
                ->middleware('permission:grupos-permissoes.editar')
                ->name('edit');

            Route::put('/{role}', [GrupoPermissaoController::class, 'update'])
                ->middleware('permission:grupos-permissoes.editar')
                ->name('update');
        });
    });
});

require __DIR__.'/auth.php';
