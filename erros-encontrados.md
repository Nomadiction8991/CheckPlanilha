# Erros Encontrados - Check Planilha Imobilizado

## Resumo
- Críticos: 0
- Altos: 11
- Médios: 20
- Baixos: 0

## Erros por Categoria
### Segurança (4)
### Lógica (29)
### Performance (0)
### Code Smell (0)

## Detalhes dos Erros

### FILTRO-001 - Status desconhecido aparecia como filtro ativo sem filtrar produtos
**Arquivo**: `app/DTO/ProductFilters.php` (`fromRequest`)
**Severidade**: 🟢 BAIXO
**Categoria**: Lógica / filtros de produtos
**Status**: Corrigido nesta rodada.
**Descrição**: O parâmetro `status` aceitava qualquer texto da URL, mas a consulta só aplicava quatro status conhecidos. Um valor inválido deixava a listagem sem filtro enquanto era mantido nos links e exibido como critério ativo.
**Risco/Impacto**: Uma URL desatualizada ou digitada incorretamente podia sugerir que a listagem estava filtrada quando todos os produtos continuavam visíveis.
**Correção**: Descartar valores fora das opções oferecidas pelo serviço de produtos. Teste de regressão confirma que o valor inválido não é mantido nem contado como critério.
**Prioridade de Correção**: ✅ Resolvido

### AUTH-007 - Sessão Laravel mantinha administrações revogadas
**Arquivo**: `app/Http/Middleware/RequireLegacySession.php` (`handle`)
**Severidade**: 🟠 ALTO
**Categoria**: Segurança / escopo entre administrações
**Status**: Corrigido nesta rodada.
**Descrição**: A ponte da sessão nativa recarregava as administrações permitidas do cadastro, mas as sessões Laravel comuns mantinham os valores e o sinalizador de administrador gravados no login. Ao revogar uma administração ou rebaixar uma conta, os serviços protegidos por `legacy.auth` continuavam usando o escopo antigo.
**Risco/Impacto**: Uma sessão ainda ativa podia consultar ou operar em administrações cujo acesso já havia sido removido.
**Correção**: O middleware recarrega a administração, a lista permitida, o estado de administrador e as permissões do usuário ativo em cada requisição autenticada. Se esses dados não puderem ser confirmados em produção, limpa os privilégios da sessão e nega a requisição. O teste de regressão reproduz uma sessão com escopo e permissões obsoletos.
**Prioridade de Correção**: ✅ Resolvido

### PROD-003 - Busca não encontrava o nome editado do bem
**Arquivo**: `app/Services/LegacyProductBrowserService.php` (`paginate`)
**Severidade**: 🟡 MÉDIO
**Categoria**: Lógica / busca de produtos
**Status**: Corrigido nesta rodada.
**Descrição**: A tabela exibe o nome e o complemento atuais quando o bem foi editado, mas a busca consultava somente os campos originais. Assim, o texto visível não encontrava o registro, e o nome antigo ainda podia retornar mesmo depois de substituído.
**Risco/Impacto**: Usuários não conseguiam localizar bens pelo nome exibido na listagem ou na verificação.
**Correção**: A busca agora consulta os campos editados quando existem e usa os valores originais somente quando não há valor editado. Testes de regressão cobrem o nome atual, o complemento e o nome substituído.
**Prioridade de Correção**: Média

### SYNC-003 - Cópia inicial aceitava contagens fracionárias
**Arquivo**: `app/Sync/CopyImporter.php` (`importar`)
**Severidade**: 🟡 MÉDIO
**Categoria**: Lógica / sincronização
**Status**: Corrigido nesta rodada.
**Descrição**: O cursor do cabeçalho e a quantidade de registros do marcador final aceitavam números decimais e os convertiam para inteiro. Uma cópia malformada podia truncar o cursor ou passar pela conferência de quantidade, embora a importação já tivesse apagado os dados locais.
**Risco/Impacto**: O notebook podia perder alterações da sincronização ou substituir seus dados por uma cópia incompleta.
**Correção**: Exigir números inteiros JSON não negativos no cursor e uma quantidade inteira exatamente igual aos registros recebidos. Testes de regressão confirmam rejeição e rollback dos dados locais.
**Prioridade de Correção**: Média

### REDE-001 - O ponto de acesso desligado era exibido como ligado no Windows
**Arquivo**: `app/Support/Connection/WindowsAccessPoint.php` (`estaLigado`)
**Severidade**: 🟡 MÉDIO
**Categoria**: Lógica / modo offline
**Status**: Corrigido nesta rodada.
**Descrição**: A verificação procurava a palavra `LIGADO` em qualquer trecho da saída do PowerShell. Como a resposta desligada é `DESLIGADO`, ela também continha essa sequência e era interpretada como rede ativa.
**Risco/Impacto**: A tela podia indicar que o ponto de acesso estava ligado e mostrar instruções de conexão mesmo quando os celulares não conseguiam encontrar a rede.
**Correção**: Comparar a resposta aparada exatamente com `LIGADO`. Testes cobrem os estados ligado e desligado.
**Prioridade de Correção**: Média

### SYNC-001 - Cópia inicial podia misturar estados de momentos diferentes
**Arquivo**: `app/Sync/CopyExporter.php` (`linhas`)
**Severidade**: 🟠 ALTO
**Categoria**: Lógica / sincronização
**Status**: Corrigido nesta rodada.
**Descrição**: O exportador enviava o cabeçalho com o cursor antes de consultar as tabelas. Uma gravação concorrente podia entrar na cópia depois desse cursor e voltar a chegar na sincronização incremental.
**Risco/Impacto**: O notebook podia receber um estado novo na cópia inicial e, em seguida, tratar o mesmo evento como alteração concorrente, criando conflito artificial.
**Correção**: Capturar o cursor e todos os registros em uma transação `REPEATABLE READ`, preparar o NDJSON em armazenamento temporário e só iniciar o envio após a leitura consistente terminar. Coberto por teste que insere um registro após o cursor ser calculado.
**Prioridade de Correção**: Alta

### AUTH-004 - Escopo de usuário podia guardar administrações inexistentes
**Arquivo**: `app/Services/LegacyUserManagementService.php` (`normalizeAdministrationScopeIds`)
**Severidade**: 🟡 MÉDIO
**Categoria**: Segurança (escopo de acesso) / integridade de dados
**Status**: Corrigido nesta rodada.
**Descrição**: Usuários autorizados a gerenciar mais de uma administração podiam salvar IDs positivos que não correspondiam a nenhum cadastro. O escopo órfão ficava persistido e poderia passar a liberar acesso caso aquele ID fosse criado posteriormente.
**Risco/Impacto**: A sessão de um usuário poderia incluir administrações que não existiam quando o escopo foi salvo, além de manter referências inválidas no cadastro.
**Correção**: Rejeitar a seleção quando qualquer administração extra não existir; a alteração foi coberta por teste unitário de regressão.
**Prioridade de Correção**: Alta

### CNPJ-001 - Máscara e consulta rejeitavam CNPJ alfanumérico
**Arquivo**: `public/assets/forms/input-mask.js`, `app/Http/Requests/LookupCnpjRequest.php`
**Severidade**: 🟡 MÉDIO
**Categoria**: Lógica e usabilidade
**Status**: Corrigido nesta rodada.
**Descrição**: O cadastro já aceitava CNPJs alfanuméricos, mas a máscara removia letras e o endpoint de consulta aceitava somente números, impedindo informar e consultar inscrições emitidas no novo formato.
**Risco/Impacto**: Usuários não conseguiam preencher ou consultar CNPJs alfanuméricos válidos nos formulários de administração e igreja.
**Correção**: A máscara passou a manter e formatar letras em maiúsculas, os campos permitem teclado de texto e a consulta valida as 12 primeiras posições alfanuméricas com dois dígitos finais. Coberto por regressões da máscara, tela e endpoint.
**Prioridade de Correção**: Alta

### AUTH-003 - Sessão nativa mantinha administrações removidas do escopo do usuário
**Arquivo**: `app/Services/LegacyNativeSessionBridge.php` (`normalizeAuthenticatedPayload`)
**Severidade**: 🟠 ALTO
**Categoria**: Segurança (escopo de acesso)
**Status**: Corrigido nesta rodada.
**Descrição**: Ao importar uma sessão nativa de usuário ativo não-administrador, o bridge atualizava apenas o estado de administrador e mantinha `administracao_id` e `administracoes_permitidas` do cookie. Se o escopo do cadastro fosse reduzido, telas e operações que filtram por esses campos continuavam acessíveis até a sessão ser renovada.
**Risco/Impacto**: Uma sessão antiga podia continuar consultando dados de administrações cujo acesso já havia sido removido.
**Correção**: Recarregar o escopo administrativo do registro ativo a cada importação; se não for possível validar o usuário no banco ou se ele estiver inativo, limpar o escopo da sessão. Coberto por `test_native_session_refreshes_regular_user_administration_scope_from_database`.
**Prioridade de Correção**: 🚨 Imediata

### AUTH-002 - Sessão de usuário removido ou não verificável continuava autenticada
**Arquivo**: `app/Http/Middleware/RequireLegacySession.php` (`legacyUserIsActive`)
**Severidade**: 🟠 ALTO
**Categoria**: Segurança (autenticação)
**Status**: Corrigido nesta rodada.
**Descrição**: O middleware aceitava uma sessão com `usuario_id` positivo quando o registro não existia e também quando a consulta ao banco falhava. A exceção de acesso público ainda podia manter uma sessão mista (usuário removido com sinalizadores públicos) nas rotas protegidas.
**Risco/Impacto**: Uma sessão sem identidade ativa podia manter acesso com as permissões armazenadas anteriormente.
**Correção**: Fora do ambiente de testes, exigir que o usuário exista e esteja ativo; falhas de consulta também redirecionam ao login. Acesso público só vale quando não há `usuario_id`, e a tolerância a schema parcial fica restrita a testes. Coberto por `RequireLegacySessionTest` para usuário removido, consulta indisponível e sessão mista.
**Prioridade de Correção**: 🚨 Imediata

### PROD-001 - Bem aceita tipo de bem de outra administração
**Arquivo**: `app/Services/LegacyProductManagementService.php` (método `resolveAssetType`, usado por criar e editar bem); regra compartilhada em `app/Services/ResolvesLegacyProductScope.php` (`assertAssetTypeWithinProductScope`)
**Severidade**: 🟠 ALTO
**Categoria**: Segurança (acesso entre administrações)
**Status**: Corrigido nesta rodada.
**Descrição**: O cadastro e a edição de bem aceitavam qualquer `tipo_bem_id`, inclusive o de uma administração que o usuário não tem. A listagem de tipos de bem já restringe o usuário não-administrador à própria administração e aos tipos compartilhados (sem administração), e a gestão de tipos de bem recusa edição fora desse escopo; o cadastro de bem não aplicava a mesma regra.
**Risco/Impacto**: Um bem de uma igreja pode ficar classificado com tipo que pertence a outra administração, e o usuário pode usar tipos que não enxerga na tela.
**Recomendação**: Aplicar ao tipo de bem a mesma checagem de escopo usada para igreja e administração: sem administração vale para todos; com administração, só se estiver entre as administrações permitidas da sessão. Administrador global continua sem restrição.
**Padrão Recorrente**: Sim — qualquer ID de cadastro vindo do formulário (igreja, dependência, tipo de bem) precisa da checagem de escopo antes de gravar.
**Prioridade de Correção**: 🚨 Imediata

### PROD-002 - Telas de edição por ID exibiam cadastros fora do escopo
**Arquivos**: `app/Http/Controllers/LegacyProductController.php`, `app/Http/Controllers/LegacyDepartmentController.php`, `app/Http/Controllers/LegacyChurchController.php` e `app/Http/Controllers/LegacyAssetTypeController.php` (métodos `edit`)
**Severidade**: 🟠 ALTO
**Categoria**: Segurança (acesso entre administrações)
**Status**: Corrigido nesta rodada.
**Descrição**: As rotas aceitavam IDs por model binding e renderizavam as páginas antes de validar se o produto, dependência, igreja ou tipo de bem pertencia a uma administração permitida. As gravações já verificavam o escopo, mas o acesso direto às telas revelava os dados dos cadastros.
**Risco/Impacto**: Usuários com permissão para editar esses cadastros podiam ler dados de outras administrações alterando o ID da URL.
**Correção**: As páginas agora usam as opções ou a consulta de escopo já aplicadas pelo serviço e respondem 404 quando o cadastro não pertence ao escopo. Há testes de regressão para os acessos por ID fora dele.
**Prioridade de Correção**: 🚨 Imediata

### REL-001 - Relatório corrompe valores com cifrão ($) no preenchimento

**Arquivo**: `resources/legacy-reports/fillers/bootstrap.php` (linha 37, função `appReportPreencherCampoPorName`)
**Severidade**: 🟠 ALTO
**Categoria**: Lógica
**Status**: Corrigido nesta rodada.
**Descrição**: O valor do campo já escapado com `htmlspecialchars` era interpolado cru no *replacement* do `preg_replace`, onde a sequência `$N` é interpretada como backreference. Um produto chamado `Caixa $100` era entregue como `Caixa 0` no HTML do relatório. Afetava os três caminhos do helper (textarea, input com value, input sem value) e, por consequência, todos os fillers 14.1 a 14.8 que passam pelo helper — inclusive descrição do bem, fornecedor, valor de nota e nome do responsável.

**Risco/Impacto**: O documento de inventário da igreja é impresso/exportado com valor diferente do cadastrado, sem nenhum erro visível para o usuário.

**Recomendação**: Escapar o cifrão do replacement com `addcslashes($valor, '\\$')` logo após o `htmlspecialchars`. O round-trip do valor fica literal em textarea e input.

**Padrão Recorrente**: Sim — qualquer interpolação de valor de usuário em replacement de `preg_replace` precisa do escape.
**Prioridade de Correção**: 🚨 Imediata

### REL-002 - Atalho da posição de estoque permanecia visível após revogação da permissão
**Arquivo**: `app/Services/LegacyReportService.php` (`listAvailableReports`)
**Severidade**: 🟡 MÉDIO
**Categoria**: UI/UX e permissões
**Status**: Corrigido nesta rodada.
**Descrição**: A lista de relatórios consultava `legacy_permissions` diretamente na sessão para decidir se mostrava a posição de estoque. Após a permissão ser revogada, essa cópia podia continuar habilitando o atalho, embora o middleware já negasse a rota com base nas permissões atuais.
**Risco/Impacto**: O usuário via um relatório indisponível e recebia uma negativa ao abri-lo.
**Correção**: Consultar `LegacyPermissionServiceInterface::can('reports.changes.view')`, que resolve a permissão atual também usada pela autorização das rotas. Coberto por `testListAvailableReportsHidesPositionWhenPermissionWasRevokedDespiteStaleSession`.
**Prioridade de Correção**: ✅ Resolvido

### CNPJ-001 - Indisponibilidade do provedor era exibida como CNPJ inexistente
**Arquivos**: `app/Services/CnpjLookupService.php`, `app/Http/Controllers/CnpjLookupController.php`
**Severidade**: 🟡 MÉDIO
**Categoria**: Lógica
**Status**: Corrigido nesta rodada.
**Descrição**: A consulta tratava qualquer resposta diferente de 2xx, timeout e falha de conexão como ausência do cadastro. Uma falha HTTP 500 ou uma indisponibilidade do provedor retornava a mesma resposta 404 usada para CNPJ não encontrado.
**Risco/Impacto**: O usuário podia concluir que o CNPJ não existe e deixar de tentar novamente, mesmo quando a consulta externa estava temporariamente indisponível.
**Correção**: Somente o 404 do provedor continua significando cadastro inexistente. Falhas de conexão, respostas HTTP não exitosas e payloads inválidos agora retornam erro 502 com mensagem acionável e sem detalhes externos. Testes cobrem resposta 500, timeout e a resposta 404 legítima.
**Prioridade de Correção**: ✅ Resolvido

### PAG-001 - Número de página extremo fazia a listagem retornar a primeira página
**Arquivo**: `app/Support/QueryParameter.php`, DTOs de filtros de listagem e `app/Services/LegacyAuditTrailService.php`
**Severidade**: 🟡 MÉDIO
**Categoria**: Lógica
**Status**: Corrigido nesta rodada.
**Descrição**: Os seis filtros de listagem limitavam a página, mas a tela de auditoria ainda passava valores extremos para `Collection::forPage()`. O cálculo do offset podia virar `float` e lançar `TypeError` em `array_slice()`.
**Risco/Impacto**: Uma URL com número de página extremo podia derrubar a tela de auditoria com erro HTTP 500.
**Correção**: Limitar a página ao maior valor cujo offset ainda cabe em um inteiro nos filtros de administrações, tipos de bem, igrejas, dependências, produtos, usuários e no serviço de auditoria. Coberto por `testPaginateHandlesExtremelyLargePageNumbers`, `test_list_filters_cap_page_numbers_before_offset_overflow` e `testPaginateCapsExtremelyLargePageNumbersBeforeSlicing`.
**Prioridade de Correção**: ✅ Resolvido

### IMP-002 - Acentos corrompidos quando a amostra de encoding corta um caractere UTF-8
**Arquivo**: `app/Services/CsvParserService.php` (método `lerCsv`, detecção de encoding)
**Severidade**: 🟠 ALTO
**Categoria**: Lógica
**Status**: Corrigido nesta rodada.
**Descrição**: A detecção de encoding lia só os primeiros 8192 bytes do CSV. Se o corte caísse no meio de um caractere UTF-8 (ex.: `Ç`, `é`), a validação em modo estrito falhava e o arquivo inteiro era tratado como ISO-8859-1. Resultado: `CONGREGAÇÃO` virava `CONGREGAÃÃO` em todas as linhas.
**Risco/Impacto**: Nomes de localidade, dependência e bem saem com acentos trocados na prévia e no cadastro, sem erro visível. Só aparece em arquivos maiores que 8 KB com acento perto do limite.
**Recomendação**: Aparar, da amostra de detecção, os bytes de um caractere UTF-8 incompleto antes de chamar `mb_detect_encoding`.
**Padrão Recorrente**: Sim — qualquer leitura por janela fixa de bytes precisa respeitar a fronteira de caractere.
**Prioridade de Correção**: 🚨 Imediata

### LEG-001 - Reconhecer admin pela sessão nas listagens

**Arquivo**: `resources/views/administrations/index.blade.php` (linha 7)
**Severidade**: 🟠 ALTO
**Categoria**: Lógica
**Status**: Corrigido nesta rodada.
**Descrição**: As listagens administrativas dependiam só de `legacySessionUser['is_admin']`. Quando o composer do layout retornava sessão parcial ou `legacySessionUser` vinha nulo, o usuário administrador perdia botões de criar e editar em várias telas.

**Risco/Impacto**: A interface ficava inconsistente e o administrador não conseguia acessar ações que deveriam estar liberadas.

**Recomendação**: Usar um fallback explícito com `session('is_admin')` nas telas de listagem ou normalizar a sessão no composer. Correção aplicada nas views afetadas.

**Padrão Recorrente**: Sim
**Prioridade de Correção**: 🚨 Imediata

### IMP-001 - Gravar igreja da importação como nulo

**Arquivo**: `app/Http/Requests/StoreSpreadsheetImportRequest.php` (linha 49)
**Severidade**: 🟠 ALTO
**Categoria**: Lógica
**Status**: Corrigido nesta rodada.
**Descrição**: A importação multi-igreja estava enviando `churchId = 0` para `importacoes.comum_id`. Como essa coluna tem chave estrangeira para `comums.id`, o insert falha ou grava um valor inválido em produção quando não há igreja base.

**Risco/Impacto**: A análise da planilha não avança e o fluxo de importação quebra assim que tenta registrar a importação.

**Recomendação**: Persistir `comum_id` como `NULL` quando não existir igreja base e continuar usando `0` apenas como fallback interno no parser.

**Padrão Recorrente**: Sim
**Prioridade de Correção**: 🚨 Imediata

### LEG-002 - Troca de igreja sem validação de escopo do usuário

**Arquivo**: `app/Services/LegacyAuthSessionService.php` (linha 124-136) e `app/Http/Controllers/LegacyRouteCompatibilityController.php` (linha 429-431)
**Severidade**: 🟠 ALTO
**Categoria**: Segurança / Lógica
**Status**: Corrigido nesta rodada.
**Descrição**: O método `switchChurch()` aceita qualquer `comum_id` existente e grava diretamente em sessão. O endpoint compatível `usersSelectChurch()` usa o mesmo fluxo sem validar se a igreja selecionada pertence ao escopo permitido do usuário autenticado. Como o restante do sistema usa `session('comum_id')` para filtrar listagens, um usuário autenticado pode pivotar o contexto para outra igreja sem restrição adicional.

**Código Atual**:
```php
public function switchChurch(int $churchId): void
{
    if ($churchId <= 0) {
        throw new RuntimeException('Igreja inválida.');
    }

    /** @var Comum|null $church */
    $church = Comum::query()->find($churchId);
    if ($church === null) {
        throw new RuntimeException('Igreja não encontrada.');
    }

    /** @var Usuario|null $user */
    $user = Usuario::query()->find((int) Session::get('usuario_id', 0));
    if ($user === null) {
        throw new RuntimeException('Sessão inválida.');
    }

    if (!$user->isAdministrator() && (int) $user->comum_id !== $churchId) {
        throw new RuntimeException('Igreja fora do escopo permitido.');
    }

    Session::put('comum_id', $churchId);
}
```

**Risco/Impacto**: A sessão passa a apontar para uma igreja arbitrária. Em telas e ações que confiam apenas no `comum_id` da sessão, isso pode expor dados de outra unidade ou permitir operações fora do escopo pretendido.

**Recomendação**:
```php
Session::put('comum_id', $churchId);
```

**Padrão Recorrente**: Não
**Prioridade de Correção**: 🚨 Imediata

### LEG-003 - Logout público exposto via GET sem proteção CSRF

**Arquivo**: `routes/web.php` (linha 21-23) e `app/Http/Controllers/PublicAccessController.php` (linha 59-68)
**Severidade**: 🟡 MÉDIO
**Categoria**: Segurança / Lógica
**Status**: Corrigido nesta rodada.
**Descrição**: O endpoint `/logout-publico` executa alteração de sessão via `GET`. Isso permite que qualquer navegação, pré-carregamento ou requisição cruzada dispare o logout sem intenção explícita do usuário. Por ser uma ação com efeito colateral, o método deveria exigir `POST` e token CSRF.

**Código Atual**:
```php
Route::post('/logout-publico', [PublicAccessController::class, 'logout'])->name('public.access.logout');
```

**Risco/Impacto**: O usuário pode perder o contexto público inesperadamente, o que quebra o fluxo e abre margem para CSRF por navegação involuntária.

**Recomendação**: Manter a rota em `POST` com proteção CSRF do grupo `web` e atualizar qualquer formulário/link que ainda chame o endpoint por `GET`.

**Padrão Recorrente**: Não
**Prioridade de Correção**: ⚠️ Próxima Sprint

### AUD-001 - Auditoria gravando em diretório não gravável

**Arquivo**: `app/Services/LegacyAuditTrailService.php` (linha 20)
**Severidade**: 🟠 ALTO
**Categoria**: Lógica / Operação
**Status**: Corrigido nesta rodada.
**Descrição**: O log de auditoria era apontado para `storage/app/private/audits/audit-log.jsonl`, mas o diretório e o arquivo estavam com dono `root` e sem permissão de escrita para o processo da aplicação. Como o serviço engolia falhas em `record()`, a tela mostrava apenas o histórico já existente no arquivo, dando a impressão de dados fictícios.

**Risco/Impacto**: Nenhum evento novo era persistido, então a página de auditoria ficava desatualizada e enganosa.

**Recomendação**: Resolver o storage para um caminho realmente gravável com fallback seguro e evitar falha silenciosa no writer. Correção aplicada no serviço.

**Padrão Recorrente**: Sim
**Prioridade de Correção**: 🚨 Imediata

### AUD-002 - Sincronização confirma auditoria sem persistir o lote

**Arquivo**: `app/Sync/AuditLog.php` (`acrescentar`)
**Severidade**: 🟠 ALTO
**Categoria**: Lógica / Operação
**Status**: Corrigido nesta rodada.
**Descrição**: O serviço ignorava o resultado da gravação do histórico no fluxo de sincronização. Quando o arquivo não podia ser aberto ou escrito, a API ainda respondia como se os eventos tivessem sido recebidos; o notebook então não tinha motivo para reenviá-los.

**Risco/Impacto**: Eventos de auditoria podiam faltar no servidor sem sinal de falha para o notebook.

**Recomendação**: Confirmar a abertura, o bloqueio, a gravação integral e o flush do arquivo, retornando erro operacional quando qualquer etapa falhar.

**Padrão Recorrente**: Sim — gravadores de auditoria não devem confirmar eventos antes de verificar sua persistência.
**Prioridade de Correção**: 🚨 Imediata

### IMP-003 - Dependência com acento duplicada ou ignorada na importação

**Arquivo**: `app/Services/CsvParserService.php` (`carregarTodasDependencias`, `analisarTodasAsLinhas`, `detectarExclusoes`), `app/Services/ImportacaoService.php` (`buscarOuCriarDependenciaCached`, `buscarOuCriarDependencia`), `app/Services/LegacySpreadsheetImportService.php` (agrupamento por dependência)
**Severidade**: 🟡 MÉDIO
**Categoria**: Lógica
**Status**: Corrigido nesta rodada.
**Descrição**: A tela de cadastro grava o nome da dependência com `mb_strtoupper` (ex.: `SALA DE REUNIÃO`), mas a importação normalizava com `strtoupper`, que não converte letras acentuadas. A planilha com `Sala de Reunião` virava `SALA DE REUNIão`, que não casava com o cadastro: a importação criava uma dependência duplicada com acento quebrado e a prévia deixava de sugerir a exclusão dos produtos daquela dependência.

**Risco/Impacto**: Duplicatas de dependência no cadastro e exclusões sugeridas incorretamente na prévia, sem erro visível.

**Recomendação**: Normalizar nomes de dependência com `mb_strtoupper` em todas as pontas (banco, planilha e cache). Correção aplicada nos serviços listados.

**Padrão Recorrente**: Sim — qualquer normalização de texto com acento precisa ser multibyte (`mb_strtoupper`), nunca `strtoupper`.
**Prioridade de Correção**: 🚨 Imediata

### IMP-004 - Linhas vazias do XLSX alteravam os números das linhas importadas

**Arquivo**: `app/Support/UniversalSpreadsheetReader.php` (`readXlsx`)
**Severidade**: 🟡 MÉDIO
**Categoria**: Lógica
**Status**: Corrigido nesta rodada.
**Descrição**: O leitor descartava linhas sem células e compactava os índices restantes. Se a planilha tivesse dados nas linhas 1 e 3, a terceira linha era apresentada como segunda durante a conversão para CSV, fazendo os erros apontarem para uma linha diferente da planilha original.

**Risco/Impacto**: O operador podia corrigir a linha errada ao seguir o número indicado no erro de importação.

**Recomendação**: Preservar os intervalos vazios entre os índices de linha do XLSX durante a leitura e a conversão para CSV.

**Padrão Recorrente**: Sim — ao converter planilhas, preservar a posição original das linhas para manter mensagens de erro acionáveis.
**Prioridade de Correção**: ⚠️ Próxima Sprint

### IMP-005 - XLSX importava a aba física `sheet1.xml` em vez da primeira aba do arquivo

**Arquivo**: `app/Support/UniversalSpreadsheetReader.php` (`readXlsx`)
**Severidade**: 🟡 MÉDIO
**Categoria**: Lógica
**Status**: Corrigido nesta rodada.
**Descrição**: O leitor assumia que a primeira aba declarada no arquivo era sempre `xl/worksheets/sheet1.xml`. Em planilhas reorganizadas, o `workbook.xml` pode apontar a primeira aba para outro arquivo, como `sheet2.xml`. Nesses casos, a importação lia silenciosamente o conteúdo de outra aba.

**Risco/Impacto**: A análise prévia podia usar dados de uma aba arquivada ou auxiliar, em vez do inventário visível ao abrir a planilha.

**Recomendação**: Resolver o `r:id` da primeira aba em `workbook.xml` usando as relações de `xl/_rels/workbook.xml.rels` e ler o caminho correspondente. Correção aplicada no leitor e coberta por teste com um pacote XLSX de duas abas reordenadas.

**Padrão Recorrente**: Sim — em arquivos OpenXML, a ordem declarada no workbook deve prevalecer sobre o nome físico do arquivo da aba.
**Prioridade de Correção**: ⚠️ Próxima Sprint

### IMP-006 - XLSX corrompido era lido como CSV
**Arquivo**: `app/Support/UniversalSpreadsheetReader.php` (`readXlsx`)
**Severidade**: 🟡 MÉDIO
**Categoria**: Lógica
**Status**: Corrigido nesta rodada.
**Descrição**: Arquivo com assinatura ZIP mas estrutura inválida (upload truncado ou corrompido) caía no leitor de CSV e virava linhas com bytes binários.
**Risco/Impacto**: A análise prévia mostrava dados sem sentido ou um erro genérico de coluna faltando, em vez de orientar o usuário a reenviar a planilha.
**Recomendação**: Lançar `RuntimeException` com mensagem acionável no ponto do fallback. Coberto por teste de unidade e de feature no fluxo de upload.
**Padrão Recorrente**: Não
**Prioridade de Correção**: ⚠️ Próxima Sprint

### LOC-001 - Nomes de UF e cidade perdiam minúsculas das partículas (de, da, do)

**Arquivo**: `app/Http/Controllers/BrazilLocalityController.php` (`normalizeCityName`, usado por estados e cidades)
**Severidade**: 🟡 MÉDIO
**Categoria**: Lógica
**Status**: Corrigido nesta rodada.
**Descrição**: A capitalização em modo título deixava "Do", "Da", "De" em nomes como "Mato Grosso do Sul" e "São João da Barra", virando "Mato Grosso Do Sul" e "São João Da Barra".
**Risco/Impacto**: Listas de UF e cidade mostravam nomes com grafia errada, o que o usuário percebe ao escolher a localidade.
**Recomendação**: Manter em minúsculas as partículas `de`, `da`, `do`, `das`, `dos` quando não forem a primeira palavra. Coberto por testes de estado e de cidade em `tests/Feature/BrazilLocalityControllerTest.php`.
**Padrão Recorrente**: Não
**Prioridade de Correção**: ⚠️ Próxima Sprint

### LOC-003 - "d'" depois de hífen saía com "D" maiúsculo (ex.: Olhos-D'água)
**Arquivo**: `app/Http/Controllers/BrazilLocalityController.php` (`normalizeCityName`, usado por estados e cidades)
**Severidade**: 🟡 MÉDIO
**Categoria**: Lógica
**Status**: Corrigido nesta rodada.
**Descrição**: A regra que mantém `d'` minúsculo só olhava para espaço antes da partícula. `OLHOS-D'ÁGUA` saía como `Olhos-D'água`, grafia que foge do padrão `Olhos-d'Água` usado pela regra de `d'`.
**Risco/Impacto**: Nome de cidade com grafia errada na lista de localidades, visível ao escolher a cidade no cadastro.
**Recomendação**: Aceitar hífen como fronteira antes de `d'` na regra de capitalização. Coberto por `testCitiesKeepsApostropheParticleLowercaseAfterHyphen` em `tests/Feature/BrazilLocalityControllerTest.php`.
**Padrão Recorrente**: Sim — regras de capitalização por fronteira de palavra precisam considerar hífen além do espaço.
**Prioridade de Correção**: ⚠️ Próxima Sprint

### LOC-002 - Nomes com "d'" (apóstrofo) saíam com "D" e "A/O" minúsculos
**Arquivo**: `app/Http/Controllers/BrazilLocalityController.php` (`normalizeCityName`, usado por estados e cidades)
**Severidade**: 🟡 MÉDIO
**Categoria**: Lógica
**Status**: Corrigido nesta rodada.
**Descrição**: A capitalização em modo título transformava `OLHO D'ÁGUA` em `Olho D'água` e `SANTA BÁRBARA D'OESTE` em `Santa Bárbara D'oeste`. A grafia oficial é `Olho d'Água` e `Santa Bárbara d'Oeste`.
**Risco/Impacto**: Nomes de cidade com grafia errada na lista de localidades, visível ao escolher a cidade no cadastro.
**Recomendação**: Após a capitalização, manter `d'` minúsculo e a letra seguinte em maiúscula, quando precedido de espaço. Coberto por `testCitiesKeepsApostropheParticleFormatting` em `tests/Feature/BrazilLocalityControllerTest.php`.
**Padrão Recorrente**: Não
**Prioridade de Correção**: ⚠️ Próxima Sprint

### LOC-004 - Conjunção "e" em nomes de cidade saía com "E" maiúsculo (ex.: Passa e Fica)
**Arquivo**: `app/Http/Controllers/BrazilLocalityController.php` (`PARTICULAS`, usado por `normalizeCityName`)
**Severidade**: 🟡 MÉDIO
**Categoria**: Lógica
**Status**: Corrigido nesta rodada.
**Descrição**: A lista de partículas minúsculas tinha `de`, `da`, `do`, `das` e `dos`, mas não `e`. `PASSA E FICA` saía como `Passa E Fica`, enquanto a grafia oficial do IBGE (código 2409100) é `Passa e Fica`.
**Risco/Impacto**: Nome de cidade com grafia errada na lista de localidades, visível ao escolher a cidade no cadastro.
**Recomendação**: Incluir `e` em `PARTICULAS`. Coberto por `testCitiesKeepsConjunctionELowercase` em `tests/Feature/BrazilLocalityControllerTest.php`.
**Padrão Recorrente**: Sim — listas de palavras minúsculas em nomes próprios precisam ser conferidas contra a grafia oficial do IBGE, não só contra as partículas mais comuns.
**Prioridade de Correção**: ⚠️ Próxima Sprint

### IMP-007 - XLSX com ZIP válido, mas sem aba legível, era tratado como planilha vazia
**Arquivo**: `app/Support/UniversalSpreadsheetReader.php` (`readXlsx`)
**Severidade**: 🟡 MÉDIO
**Categoria**: Lógica
**Status**: Corrigido nesta rodada.
**Descrição**: Quando o arquivo tinha uma assinatura XLSX e um contêiner ZIP válido, mas não continha uma aba XML legível, o leitor retornava uma lista vazia. O fluxo não diferenciava uma planilha realmente vazia de uma estrutura inválida e podia exibir um erro genérico de dados ausentes.
**Risco/Impacto**: O operador recebia uma orientação pouco útil e não sabia que precisava reparar ou reenviar o arquivo.
**Recomendação**: Informar que a aba está ausente ou inválida e orientar a salvar novamente como `.xlsx`. Coberto por `test_xlsx_zip_sem_aba_legivel_gera_erro_acionavel`.
**Padrão Recorrente**: Não
**Prioridade de Correção**: ⚠️ Próxima Sprint

### IMP-008 - XLSX vazio era encaminhado como arquivo binário ao parser CSV
**Arquivo**: `app/Support/UniversalSpreadsheetReader.php` (`ensureCsv`)
**Severidade**: 🟡 MÉDIO
**Categoria**: Lógica
**Status**: Corrigido nesta rodada.
**Descrição**: Quando a primeira aba válida de um XLSX não tinha linhas de dados, `readXlsx()` retornava uma lista vazia e `ensureCsv()` devolvia o caminho original. O fluxo de upload então encaminhava o conteúdo ZIP ao parser CSV, em vez de produzir um CSV vazio para o parser informar que não há dados.
**Risco/Impacto**: O usuário recebia uma falha de leitura confusa ou erro interno ao enviar uma planilha válida, mas vazia.
**Recomendação**: Converter também resultados vazios e deixar a validação de conteúdo informar que não há dados importáveis. Coberto por teste com um XLSX válido sem linhas.
**Padrão Recorrente**: Sim — arquivos binários reconhecidos devem ser convertidos mesmo quando não contêm registros.
**Prioridade de Correção**: ⚠️ Próxima Sprint

### IMP-009 - Diagnóstico não reconhecia cabeçalho com acento em ambiente sem transliteração Unicode
**Arquivo**: `app/Services/SpreadsheetDiagnosticService.php` (`normalizeString`)
**Severidade**: 🟡 MÉDIO
**Categoria**: Lógica
**Status**: Corrigido nesta rodada.
**Descrição**: Em ambientes onde `iconv` translitera `ó` como apóstrofo, o cabeçalho `Código` virava `c'odigo`. A detecção automática não encontrava a linha de cabeçalho e contava o próprio cabeçalho como produto, além de gerar uma contagem incorreta.
**Risco/Impacto**: A pré-validação de CSV podia exibir contagens enganosas e aceitar cabeçalho como registro patrimonial.
**Recomendação**: Normalizar apóstrofos introduzidos pela transliteração antes de comparar rótulos. Coberto pelo teste de diagnóstico de CSV válido.
**Padrão Recorrente**: Sim — normalização de texto para comparação deve tolerar diferenças de transliteração entre ambientes.
**Prioridade de Correção**: ⚠️ Próxima Sprint

### IMP-010 - XLSX descartava zeros à esquerda indicados pelo formato numérico
**Arquivo**: `app/Support/UniversalSpreadsheetReader.php` (`readXlsx`)
**Severidade**: 🟡 MÉDIO
**Categoria**: Importação de planilhas
**Status**: Corrigido nesta rodada.
**Descrição**: O leitor ignorava formatos numéricos personalizados como `00000` e retornava o valor interno da célula. Um código `42` exibido na planilha como `00042` era importado sem os zeros.
**Risco/Impacto**: Códigos patrimoniais que usam zeros à esquerda podiam deixar de corresponder ao cadastro existente ou perder a identificação original.
**Recomendação**: Aplicar formatos compostos somente por zeros aos valores inteiros das células numéricas. Coberto por `test_le_xlsx_preservando_zeros_a_esquerda_definidos_no_formato_da_celula`.
**Padrão Recorrente**: Sim — a importação deve preservar a representação exibida dos identificadores numéricos.
**Prioridade de Correção**: ✅ Resolvido

### IMP-022 - Exportações CSV permitiam fórmulas após quebras de linha ou em campos sem sanitização
**Arquivo**: `app/Support/LegacyCsvSanitizer.php`, `app/Services/LegacySpreadsheetImportService.php` e `app/Services/LegacyReportService.php`
**Severidade**: 🟡 MÉDIO
**Categoria**: Segurança
**Status**: Corrigido nesta rodada.
**Descrição**: O sanitizador não neutralizava fórmulas iniciadas por LF, espaços de controle ou caracteres de largura completa. Além disso, alguns campos externos eram escritos sem sanitização: código do erro de importação, código no backup da posição e códigos dos tipos de bem original/editado no CSV do formulário 14.6.
**Risco/Impacto**: Dados importados ou editáveis podiam ser interpretados como fórmulas ao abrir os CSVs de erros, relatórios ou auditoria em um programa de planilhas.
**Recomendação**: Neutralizar os iniciadores de fórmula no primeiro caractere não branco e aplicar a proteção a todos os campos externos escritos nas exportações. Coberto por testes unitários do sanitizador e regressões para exportação de erros, posição de verificação e tipos de bem do formulário 14.6.
**Padrão Recorrente**: Sim — cada campo externo em CSV precisa passar pela mesma proteção, inclusive códigos e valores que pareçam identificadores.
**Prioridade de Correção**: ✅ Resolvido

### PROD-003 - Checklist em lote aceitava produto de outra igreja ou inexistente
**Arquivo**: `app/Services/LegacyProductUtilityService.php` (`saveVerificationChecklist`)
**Severidade**: 🟡 MÉDIO
**Categoria**: Lógica (integridade do checklist)
**Status**: Corrigido nesta rodada.
**Descrição**: O salvamento em lote ignorava o resultado das atualizações filtradas pela igreja e contava o item como processado mesmo quando o produto não existia ou pertencia a outra igreja. O endpoint de atualização individual já respondia 404 para um produto fora da igreja.
**Risco/Impacto**: A tela podia confirmar que o checklist foi salvo enquanto deixava itens sem persistir; mudanças anteriores do mesmo lote podiam ser parcialmente gravadas.
**Recomendação**: Validar todos os produtos dentro da igreja selecionada antes de atualizar qualquer item, mantendo a transação atômica. Coberto por `testChecklistRejectsProductsOutsideTheSelectedChurchWithoutSavingPartialChanges`.
**Padrão Recorrente**: Sim — IDs de produto enviados pelo cliente precisam pertencer à igreja do lote, como já ocorre na atualização individual.
**Prioridade de Correção**: ✅ Resolvido

### AUTH-001 - Recuperação aceitava telefone sem dígitos em cadastro sem telefone
**Arquivo**: `app/Services/LegacyPasswordRecoveryService.php` (`recover`)
**Severidade**: 🟠 ALTO
**Categoria**: Segurança
**Status**: Corrigido nesta rodada.
**Descrição**: A recuperação normalizava o telefone removendo tudo que não fosse dígito e comparava os resultados. Assim, uma entrada como `abc` virava string vazia e correspondia a um telefone `NULL` ou vazio no cadastro.
**Risco/Impacto**: Uma pessoa que conhecesse o CPF e o e-mail de uma conta sem telefone cadastrado podia trocar a senha usando uma entrada sem número.
**Recomendação**: Rejeitar telefones sem dígitos antes de buscar a conta. Coberto por `LegacyPasswordRecoveryServiceTest`, que confirma rejeição, senha inalterada e ausência de e-mail enviado.
**Padrão Recorrente**: Comparações de dados de recuperação devem rejeitar valores vazios após normalização.
**Prioridade de Correção**: ✅ Resolvido

### LOC-005 - "Sant'Ana" saía com "a" minúsculo após o apóstrofo
**Arquivo**: `app/Http/Controllers/BrazilLocalityController.php` (`normalizeCityName`, usado por estados e cidades)
**Severidade**: 🟡 MÉDIO
**Categoria**: Lógica
**Status**: Corrigido nesta rodada.
**Descrição**: A capitalização em modo título gerava "Sant'ana do Livramento". A regra de apóstrofo só cobria "D'" depois de espaço ou hífen, então nomes com apóstrofo no meio da palavra ficavam com a letra seguinte minúscula.
**Risco/Impacto**: Cidades com apóstrofo no meio do nome apareciam com grafia diferente da oficial na lista de cidades.
**Recomendação**: Letra depois de apóstrofo precedido de duas letras fica maiúscula ("Sant'Ana"). Coberto por `testCitiesCapitalizesLetterAfterApostropheInsideWord` em `tests/Feature/BrazilLocalityControllerTest.php`.
**Padrão Recorrente**: Sim — regras de capitalização precisam ser conferidas para todo tipo de fronteira de palavra (espaço, hífen e apóstrofo).
**Prioridade de Correção**: ✅ Resolvido

### LOC-006 - Partículas ligadas por hífen saíam com maiúscula (ex.: Passo-Do-Sobrado)
**Arquivo**: `app/Http/Controllers/BrazilLocalityController.php` (`normalizeCityName`, usado por estados e cidades)
**Severidade**: 🟡 MÉDIO
**Categoria**: Lógica
**Status**: Corrigido nesta rodada.
**Descrição**: A regra que mantém `de`, `da`, `do`, `das`, `dos` e `e` minúsculos só separava palavras por espaço. `PASSO-DO-SOBRADO` saía como `Passo-Do-Sobrado` e `PEDRA-DA-ÁGUA` como `Pedra-Da-Água`.
**Risco/Impacto**: Nome de cidade com grafia errada na lista de localidades, visível ao escolher a cidade no cadastro.
**Recomendação**: Tratar hífen como fronteira de palavra também para as partículas, mantendo a primeira palavra do nome em maiúscula. Coberto por `testCitiesKeepsParticlesLowercaseWhenJoinedByHyphen` em `tests/Feature/BrazilLocalityControllerTest.php`.
**Padrão Recorrente**: Sim — mesma família de LOC-003: fronteira de palavra precisa considerar hífen além do espaço.
**Prioridade de Correção**: ✅ Resolvido

### LOC-007 - "D'" no início do nome saía com a letra seguinte minúscula (ex.: D'ávila)
**Arquivo**: `app/Http/Controllers/BrazilLocalityController.php` (`normalizeCityName`, usado por estados e cidades)
**Severidade**: 🟡 MÉDIO
**Categoria**: Lógica
**Status**: Corrigido nesta rodada.
**Descrição**: A regra de apóstrofo exigia duas letras antes dele, então "D'ÁVILA" saía como "D'ávila". Uma sílaba só antes do apóstrofo ficava sem a letra seguinte maiúscula.
**Risco/Impacto**: Cidades com "D'" ou "O'" no início do nome apareciam com grafia diferente da oficial na lista de cidades.
**Recomendação**: Letra depois de qualquer apóstrofo entre letras fica maiúscula ("D'Ávila", "Sant'Ana"). Coberto por `testCitiesCapitalizesLetterAfterApostropheAtNameStart` em `tests/Feature/BrazilLocalityControllerTest.php`.
**Padrão Recorrente**: Sim — regras de capitalização precisam ser conferidas para todo tipo de fronteira de palavra (espaço, hífen e apóstrofo).
**Prioridade de Correção**: ✅ Resolvido

### LOC-008 - Apóstrofo tipográfico (’) não seguia a regra de "d'" e "D'" (ex.: "SANTA CRUZ D’OESTE")
**Arquivo**: `app/Http/Controllers/BrazilLocalityController.php` (`normalizeCityName`, usado por estados e cidades)
**Severidade**: 🟡 MÉDIO
**Categoria**: Lógica
**Status**: Corrigido nesta rodada.
**Descrição**: As regras de apóstrofo só reconheciam o apóstrofo ASCII ('). Com o caractere tipográfico (’, U+2019), "SANTA CRUZ D’OESTE" saía como "Santa Cruz D’oeste" e "D’ÁVILA" como "D’ávila".
**Risco/Impacto**: Cidades vindas de fontes que usam o apóstrofo tipográfico saíam com grafia diferente da oficial na lista de cidades.
**Recomendação**: Tratar ' e ’ como o mesmo apóstrofo nas regras de "d'" e de letra maiúscula após apóstrofo, preservando o caractere original. Coberto por `testCitiesTreatsTypographicApostropheLikeAsciiApostrophe` em `tests/Feature/BrazilLocalityControllerTest.php`.
**Padrão Recorrente**: Sim — regras de texto que mencionam um caractere especial precisam considerar as variantes tipográficas do mesmo caractere.
**Prioridade de Correção**: ✅ Resolvido

### IMP-011 - CSV com vírgulas dentro de aspas era separado pela vírgula
**Arquivo**: `app/Support/UniversalSpreadsheetReader.php` (`readCsv`)
**Severidade**: 🟡 MÉDIO
**Categoria**: Importação de planilhas
**Status**: Corrigido nesta rodada.
**Descrição**: A detecção do separador contava vírgulas e ponto e vírgula sem considerar campos entre aspas. Em um CSV separado por ponto e vírgula, descrições com várias vírgulas faziam o leitor escolher o separador errado e dividir as colunas incorretamente.
**Risco/Impacto**: Dados de descrição e dependência podiam ser deslocados na análise da planilha sem uma mensagem de erro clara.
**Recomendação**: Comparar a consistência da quantidade de colunas nas primeiras linhas usando o próprio parser CSV, que respeita aspas. Coberto por `test_universal_reader_ignores_delimiters_inside_quoted_values` com amostra de CSV.
**Padrão Recorrente**: Sim — a detecção de estrutura CSV deve respeitar o encapsulamento de campos.
**Prioridade de Correção**: ✅ Resolvido

### IMP-012 - Pipeline de importação ainda escolhia o separador CSV contando caracteres crus
**Arquivo**: `app/Services/CsvParserService.php` (`detectarDelimitador`)
**Severidade**: 🟡 MÉDIO
**Categoria**: Importação de planilhas
**Status**: Corrigido nesta rodada.
**Descrição**: A correção IMP-011 foi aplicada só ao leitor de prévia (`UniversalSpreadsheetReader`). O parser que de fato grava a importação ainda contava todas as vírgulas e ponto e vírgulas do arquivo. Em um CSV separado por ponto e vírgula com descrições que têm mais vírgulas do que separadores, o parser escolhia a vírgula e as colunas saíam deslocadas.
**Risco/Impacto**: Os registros importados ficavam com código, descrição e localidade trocados, sem mensagem de erro.
**Recomendação**: Usar a mesma detecção por CSV parseado (campos entre aspas ignorados) nos dois caminhos. Coberto por `testLerCsvDetectaPontoVirgulaMesmoQuandoDescricaoTemMaisVirgulasQueSeparadores`.
**Padrão Recorrente**: Sim — a mesma lógica de detecção deve ser compartilhada entre leitores.
**Prioridade de Correção**: ✅ Resolvido

### IMP-013 - XLS BIFF ignorava células com strings compartilhadas
**Arquivo**: `app/Support/UniversalSpreadsheetReader.php` (`readXls`)
**Severidade**: 🟠 ALTO
**Categoria**: Importação de planilhas
**Status**: Corrigido nesta rodada.
**Descrição**: O leitor reconhecia células `LABEL` e `NUMBER`, mas ignorava `LABELSST`, que referencia os textos da tabela de strings compartilhadas (`SST`) usada por planilhas BIFF8 reais. Também não acessava fluxos CFB armazenados em mini-streams nem juntava blocos `CONTINUE` de tabelas SST grandes. Com isso, cabeçalhos e dados de arquivos `.xls` eram lidos como vazios ou incompletos.
**Risco/Impacto**: Planilhas `.xls` válidas podiam perder nomes, códigos e outros campos durante a análise, causando importação vazia ou dados incorretos.
**Recomendação**: Interpretar os fluxos CFB pequenos, a tabela `SST`, os blocos `CONTINUE` e os índices das células `LABELSST`. Coberto por amostras `.xls` reais exportadas pelo LibreOffice para fluxos curtos e longos.
**Padrão Recorrente**: Sim — o leitor deve cobrir os tipos de célula usados pelos arquivos BIFF comuns.
**Prioridade de Correção**: ✅ Resolvido

### IMP-014 - XLS BIFF ignorava números armazenados em registros RK e MULRK
**Arquivo**: `app/Support/UniversalSpreadsheetReader.php` (`readXls`)
**Severidade**: 🟠 ALTO
**Categoria**: Importação de planilhas
**Status**: Corrigido nesta rodada.
**Descrição**: O leitor reconhecia `LABEL`, `LABELSST` e `NUMBER`, mas ignorava `RK` e `MULRK`, formas compactadas que o Excel e o LibreOffice usam para gravar números inteiros e decimais. Células numéricas nesses registros ficavam vazias na leitura.
**Risco/Impacto**: Valores numéricos de planilhas `.xls` (códigos, quantidades, valores) sumiam em silêncio durante a análise, gerando importação incompleta sem aviso.
**Recomendação**: Decodificar `RK` (inteiro de 30 bits ou double truncado, com divisão por 100 quando indicado) e `MULRK` (várias células consecutivas na mesma linha). Coberto pelo arquivo `tests/Fixtures/importacao/xls-numeros-rk.xls`, gerado pelo LibreOffice com inteiros, decimais negativos e células adjacentes.
**Padrão Recorrente**: Sim — o leitor deve cobrir os tipos de célula usados pelos arquivos BIFF comuns.
**Prioridade de Correção**: ✅ Resolvido

### IMP-015 - Barra invertida antes da aspa final corrompia CSV válido
**Arquivo**: `app/Support/UniversalSpreadsheetReader.php` (`readCsv`, `detectCsvDelimiter`)
**Severidade**: 🟠 ALTO
**Categoria**: Importação de planilhas
**Status**: Corrigido nesta rodada.
**Descrição**: O leitor configurava a barra invertida como caractere de escape. Em CSV padrão, uma barra invertida literal antes da aspa que fecha um campo era interpretada como escape da aspa, fazendo o parser consumir os separadores e as linhas seguintes.
**Risco/Impacto**: Campos e registros seguintes podiam ser unidos e importados com dados deslocados ou incompletos sem aviso.
**Recomendação**: Usar escape vazio no parser e no gravador CSV, mantendo o escape de aspas no padrão de aspas duplicadas. Coberto por `csv-barra-invertida-antes-da-aspas.csv`.
**Padrão Recorrente**: Sim — entrada e saída CSV devem seguir a mesma convenção de aspas.
**Prioridade de Correção**: ✅ Resolvido

### IMP-016 - XLS com várias abas misturava as células de todas na mesma grade
**Arquivo**: `app/Support/UniversalSpreadsheetReader.php` (`readXls`, `posicaoPrimeiraAbaXls`)
**Severidade**: 🟠 ALTO
**Categoria**: Importação de planilhas
**Status**: Corrigido nesta rodada.
**Descrição**: O leitor de `.xls` percorria o stream inteiro do Workbook, com as células de todas as abas. Quando uma aba posterior tinha célula no mesmo endereço da primeira, ela sobrescrevia o valor (ex.: o cabeçalho `Patrimonio` virava `ABA2-LINHA1`).
**Risco/Impacto**: Dados da aba principal saíam trocados ou misturados com outra aba, sem nenhum aviso ao usuário.
**Recomendação**: Ler apenas a primeira aba de planilha, a partir do BOF indicado pelo registro `BOUNDSHEET` e parando no `EOF` dela, como já é feito no XLSX. Coberto por `tests/Fixtures/importacao/xls-duas-abas.xls`, gerado pelo LibreOffice.
**Padrão Recorrente**: Sim — o leitor deve respeitar os limites de cada aba, como já respeita os limites de cada bloco de registros.
**Prioridade de Correção**: ✅ Resolvido

### IMP-017 - XLS perdia o resultado de células com fórmula
**Arquivo**: `app/Support/UniversalSpreadsheetReader.php` (`readXls`)
**Severidade**: 🟠 ALTO
**Categoria**: Importação de planilhas
**Status**: Corrigido nesta rodada.
**Descrição**: O leitor ignorava os registros `FORMULA` e `STRING`. Células cujo conteúdo é uma fórmula ficavam vazias, mesmo com o resultado calculado gravado no arquivo, tanto para números quanto para textos.
**Risco/Impacto**: Valores calculados na planilha (ex.: quantidade multiplicada, nome concatenado) sumiam na importação sem aviso.
**Recomendação**: Ler o resultado numérico direto do `FORMULA`; quando o resultado for texto (marcador `0xFFFF` com tipo `0`), usar o `STRING` logo seguinte. Coberto por `tests/Fixtures/importacao/xls-formulas.xls`, gerado a partir de um XLSX com fórmulas convertido pelo LibreOffice.
**Padrão Recorrente**: Sim — o leitor deve cobrir todos os registros que carregam valor de célula, não só os de valor direto.
**Prioridade de Correção**: ✅ Resolvido

### IMP-018 - Uploads da mesma congregação podiam sobrescrever o arquivo uns dos outros
**Arquivo**: `app/Services/LegacySpreadsheetImportService.php` (`moveUploadedFile`)
**Severidade**: 🟠 ALTO
**Categoria**: Importação de planilhas
**Status**: Corrigido nesta rodada.
**Descrição**: O nome do arquivo armazenado era composto pela congregação e pelo segundo atual. Dois uploads da mesma congregação no mesmo segundo usavam o mesmo caminho; o segundo podia substituir a planilha ainda referenciada pela primeira importação.
**Risco/Impacto**: Uma prévia ou processamento podia ler o conteúdo de outro upload, causando importação de dados errados ou inconsistência entre análise e arquivo salvo.
**Recomendação**: Gerar um identificador aleatório por upload e cobrir o fluxo HTTP garantindo que um arquivo já existente não seja substituído. Coberto por `test_upload_http_persiste_importacao_e_analise_reais` com arquivos sentinela no padrão antigo de nome.
**Padrão Recorrente**: Sim — arquivos de importação precisam de nomes exclusivos, mesmo quando enviados no mesmo instante.
**Prioridade de Correção**: ✅ Resolvido

### IMP-019 - CSV UTF-8 com BOM alterava o nome da primeira coluna
**Arquivo**: `app/Support/UniversalSpreadsheetReader.php` (`readCsv`)
**Severidade**: 🟠 ALTO
**Categoria**: Importação de planilhas
**Status**: Corrigido nesta rodada.
**Descrição**: O leitor preservava o marcador BOM (`EF BB BF`) no início do primeiro campo de um CSV UTF-8. Com isso, o cabeçalho `Código` chegava como `﻿Código` e podia deixar de ser reconhecido durante a importação.
**Risco/Impacto**: Planilhas CSV exportadas com BOM podiam falhar no reconhecimento das colunas sem indicar que o conteúdo estava correto.
**Recomendação**: Remover o BOM somente do início do conteúdo antes de detectar o separador e interpretar os campos. Coberto por `tests/Fixtures/importacao/csv-utf8-bom.csv`.
**Padrão Recorrente**: Sim — marcadores de codificação devem ser tratados antes da leitura dos cabeçalhos.
**Prioridade de Correção**: ✅ Resolvido

### IMP-020 - CSV com metadados ASCII longos corrompia acentos em encoding legado
**Arquivo**: `app/Services/CsvParserService.php` (`lerCsv`)
**Severidade**: 🟡 MÉDIO
**Categoria**: Importação de planilhas
**Status**: Corrigido nesta rodada.
**Descrição**: A detecção do encoding analisava somente os primeiros 8 KB. Quando esse trecho continha apenas metadados ASCII e os acentos apareciam depois em Windows-1252, o parser não convertia os bytes para UTF-8.
**Risco/Impacto**: Nomes de bens e localidades podiam chegar corrompidos à prévia e à importação.
**Recomendação**: Continuar a leitura em blocos até encontrar bytes não ASCII, sem carregar o arquivo inteiro na memória. Coberto por `csv-windows1252-apos-amostra.csv`.
**Padrão Recorrente**: Sim — a detecção de encoding não pode assumir que os primeiros bytes do arquivo representam o conteúdo textual.
**Prioridade de Correção**: ✅ Resolvido

### DEP-001 - Dependência com bens vinculados podia mudar de igreja
**Arquivo**: `app/Services/LegacyDepartmentManagementService.php` (`update`)
**Severidade**: 🟠 ALTO
**Categoria**: Lógica
**Status**: Corrigido nesta rodada.
**Descrição**: A edição de uma dependência aceitava trocar a igreja (`comum_id`) mesmo quando já havia bens vinculados a ela (por `dependencia_id` ou `editado_dependencia_id`). Os bens ficavam apontando para um local de outra igreja.
**Risco/Impacto**: Relatórios, etiquetas e a listagem de bens mostravam um local que não pertence à igreja do bem. A transferência e o cadastro de bens já exigem que o local pertença à igreja; a edição de dependência era a única brecha.
**Recomendação**: Bloquear a troca de igreja enquanto houver bens vinculados; manter a renomeação dentro da mesma igreja e a troca livre quando não houver vínculo. Coberto por `testDepartmentCannotMoveToAnotherChurchWhileProductsAreLinked`, `testDepartmentWithLinkedProductsKeepsChurchWhenMoveIsBlocked` e `testDepartmentWithLinkedProductsCanStillBeRenamedInSameChurch`.
**Padrão Recorrente**: Sim — todo caminho que grava um vínculo entre registros de igrejas diferentes precisa validar que o registro vinculado pertence à mesma igreja.
**Prioridade de Correção**: ✅ Resolvido

### DEP-002 - Dependência usada só como local editado podia ser excluída
**Arquivo**: `app/Services/LegacyDepartmentManagementService.php` (`delete`)
**Severidade**: 🟠 ALTO
**Categoria**: Lógica
**Status**: Corrigido nesta rodada.
**Descrição**: A exclusão de dependência só verificava bens com `dependencia_id` igual ao local. Um bem cujo local foi editado para uma dependência (`editado_dependencia_id`) não bloqueava a exclusão, e a regra de movimentação já considerava esse vínculo.
**Risco/Impacto**: Ao apagar essa dependência, o bem perdia o local editado sem aviso: etiquetas e relatórios voltavam a mostrar o local original.
**Recomendação**: Usar a mesma checagem de vínculo nos dois campos (`dependencia_id` e `editado_dependencia_id`) em exclusão e movimentação. Coberto por `testDepartmentUsedOnlyAsEditedLocationCannotBeDeleted` e `testDepartmentUsedAsEditedLocationIsKeptWhenDeletionIsBlocked`.
**Padrão Recorrente**: Sim — toda guarda de exclusão precisa considerar tanto o vínculo original quanto o vínculo editado.
**Prioridade de Correção**: ✅ Resolvido

### PROD-002 - Tipo de bem usado só como tipo editado podia ser excluído
**Arquivo**: `app/Services/LegacyAssetTypeManagementService.php` (`delete`)
**Severidade**: 🟠 ALTO
**Categoria**: Lógica
**Status**: Corrigido nesta rodada.
**Descrição**: A exclusão de tipo de bem só verificava bens com `tipo_bem_id` igual ao tipo. Um bem com o tipo editado (`editado_tipo_bem_id`) não bloqueava a exclusão.
**Risco/Impacto**: Ao apagar esse tipo, o bem perdia o tipo editado sem aviso, e relatórios e etiquetas passavam a mostrar o tipo original.
**Recomendação**: Bloquear a exclusão quando houver bem com `tipo_bem_id` ou `editado_tipo_bem_id` igual ao tipo. Coberto por `testAssetTypeUsedOnlyAsEditedTypeCannotBeDeleted` e `testAssetTypeUsedAsEditedTypeIsKeptWhenDeletionIsBlocked`.
**Padrão Recorrente**: Sim — mesmo padrão de DEP-002.
**Prioridade de Correção**: ✅ Resolvido

### REP-001 - Atualização sem colunas permitidas gerava SQL inválido
**Arquivo**: `app/Repositories/BaseRepository.php` (`atualizar`)
**Severidade**: 🟡 MÉDIO
**Categoria**: Estabilidade
**Status**: Corrigido nesta rodada.
**Descrição**: Quando todos os campos recebidos eram removidos pela lista de colunas permitidas, ou o array estava vazio, o repositório montava `UPDATE ... SET WHERE` e o banco lançava erro de sintaxe.
**Risco/Impacto**: Uma entrada sem campos editáveis podia terminar em erro interno em vez de ser ignorada com segurança.
**Recomendação**: Retornar `false` antes de montar a consulta quando não houver colunas permitidas. Coberto por `testUpdateWithOnlyUnknownColumnsDoesNotBuildAnEmptyUpdate` e `testUpdateWithEmptyDataReturnsFalseWithoutChangingTheRecord`.
**Padrão Recorrente**: Sim — uma atualização sem campos válidos não deve emitir SQL de escrita; o caminho específico de atualização de produto já aplica essa guarda.
**Prioridade de Correção**: ✅ Resolvido

### REP-002 - Filtro de dependência ocultava bens editados sem local editado válido
**Arquivo**: `app/Services/LegacyReportService.php` (`loadChangeHistoryProducts`)
**Severidade**: 🟡 MÉDIO
**Categoria**: Lógica / Relatórios
**Status**: Corrigido nesta rodada.
**Descrição**: O histórico de alterações filtrava todo bem com `editado = 1` somente por `editado_dependencia_id`. Em registros legados sem dependência editada válida, a tela exibia a dependência original, mas o filtro por esse local não retornava o bem.
**Risco/Impacto**: Relatórios filtrados por dependência omitiam bens editados que ainda estavam no local original.
**Correção**: O filtro agora usa a dependência editada somente quando ela existe e tem descrição válida, seguindo a mesma regra usada para exibir o local. Testes cobrem o fallback ao local original e a seleção do local editado.
**Prioridade de Correção**: ✅ Resolvido

### IMP-021 - Download de erros de importação ignorava o escopo quando recebia o id da importação
**Arquivo**: `app/Services/LegacySpreadsheetImportService.php` (`downloadImportErrorsCsv`)
**Severidade**: 🟠 ALTO
**Categoria**: Segurança
**Status**: Corrigido nesta rodada.
**Descrição**: Com `importacao_id` informado, o CSV de correção de erros buscava as linhas de `import_erros` só pelo id da importação. A checagem de administração (ou de igreja) era ignorada, diferentemente da listagem de erros (`loadImportErrors`). Um usuário de outra administração, ou sem administração, baixava os erros de qualquer importação.
**Risco/Impacto**: Vazamento de código, nome, localidade e dependência de bens de outra administração.
**Recomendação**: Aplicar no ramo `importacao_id` o mesmo escopo da listagem: `whereIn` nas administrações permitidas ou filtro por igreja. Coberto por `testNonAdminCannotDownloadErrorsOfImportOutsideItsAdministration`, `testUserWithoutAdministrationScopeCannotDownloadErrorsOfAnyImport` e `testNonAdminDownloadsErrorsOfImportInsidePermittedAdministration`.
**Padrão Recorrente**: Sim — ações que recebem o id da importação sem checar escopo. Resolvido: `preview`, `confirm`, `savePreviewActions`, `bulkPreviewAction` e `processing` agora aplicam a mesma validação de responsável já usada no início e acompanhamento do processamento. Coberto por `testPreviewActionsAreDeniedForAnotherUsersImport`, `testOtherUserCannotReadPreviewOrProcessingPage` e `testOtherUserCannotApplyBulkActionOrConfirmImport`.
**Prioridade de Correção**: ✅ Resolvido

### IMP-023 - Importações antigas sem administração ficavam invisíveis no escopo da igreja
**Arquivo**: `app/Services/LegacySpreadsheetImportService.php`
**Severidade**: 🟡 MÉDIO
**Categoria**: Importação de planilhas / Acesso a dados
**Status**: Corrigido nesta rodada.
**Descrição**: A coluna `importacoes.administracao_id` foi adicionada como anulável e os registros existentes não receberam preenchimento retroativo. Os filtros de histórico, erros, resumo, download e resolução usavam apenas essa coluna; por isso, importações antigas com `comum_id` válido não apareciam para usuários da administração correta.
**Risco/Impacto**: Usuários autorizados não conseguiam consultar nem resolver erros de importações históricas, embora a igreja responsável ainda estivesse vinculada à administração permitida.
**Recomendação**: Para registros com `administracao_id` nulo, derivar o escopo da igreja vinculada e aplicar a mesma regra em todos os caminhos de consulta e alteração. Coberto por `testNonAdminSeesLegacyImportThroughPermittedChurchWhenAdministrationIdIsNull`.
**Padrão Recorrente**: Sim — campos de escopo adicionados após a criação dos registros precisam de fallback seguro quando os dados antigos ainda não foram preenchidos.
**Prioridade de Correção**: ✅ Resolvido

### IMP-024 - Limpeza de análises consultava uma conexão de banco diferente da aplicação
**Arquivo**: `app/Console/Commands/AnalysesCleanupOrphans.php` e `app/Console/Commands/SystemStorageCleanupCommand.php`
**Severidade**: 🟡 MÉDIO
**Categoria**: Importação de planilhas / Limpeza de arquivos
**Status**: Corrigido nesta rodada.
**Descrição**: Os dois comandos consultavam `importacoes` pela conexão fixa `pgsql`. A configuração de produção usa a conexão padrão MySQL; assim, análises de importações concluídas não eram removidas, e o comando de órfãos podia ignorar todas as linhas após falha de conexão.
**Risco/Impacto**: Arquivos temporários de análises concluídas acumulavam no servidor, enquanto a limpeza manual de órfãos não conseguia liberar espaço.
**Correção**: Consultas, transações e exclusões do comando de órfãos agora usam a conexão padrão da aplicação. Os testes cobrem registros nessa conexão com a conexão legada vazia ou indisponível.
**Prioridade de Correção**: ✅ Resolvido

### OPS-001 - Limpeza de temporários seguia diretórios fora do storage por link simbólico
**Arquivo**: `app/Console/Commands/SystemStorageCleanupCommand.php` e `app/Console/Commands/AnalysesCleanupOrphans.php`
**Severidade**: 🟠 ALTO
**Categoria**: Estabilidade / Integridade de arquivos
**Status**: Corrigido nesta rodada.
**Descrição**: Os comandos aceitavam seus diretórios de trabalho mesmo quando o próprio diretório era um link simbólico. A limpeza genérica podia remover arquivos antigos no destino do link, e a limpeza de análises podia apagar arquivos `analise_*.json` fora do storage configurado.
**Risco/Impacto**: Remoção de arquivos externos ao armazenamento esperado durante uma rotina de manutenção agendada ou manual.
**Correção**: Os dois comandos agora recusam diretórios de trabalho que sejam links simbólicos. Testes de regressão confirmam que arquivos nos destinos permanecem intactos.
**Prioridade de Correção**: ✅ Resolvido

### OPS-002 - Limpeza informava sucesso quando não conseguia remover arquivos
**Arquivo**: `app/Console/Commands/SystemStorageCleanupCommand.php` e `app/Console/Commands/AnalysesCleanupOrphans.php`
**Severidade**: 🟡 MÉDIO
**Categoria**: Estabilidade / Limpeza de arquivos
**Status**: Corrigido nesta rodada.
**Descrição**: Os comandos ignoravam o resultado de `unlink()` e contabilizavam o arquivo como removido mesmo quando uma falha de permissão ou do sistema de arquivos o mantinha no storage.
**Risco/Impacto**: A saída confirmava espaço liberado sem que isso tivesse ocorrido, ocultando falhas operacionais e dificultando a investigação de armazenamento acumulado.
**Correção**: A contagem agora inclui somente remoções confirmadas; falhas são exibidas e fazem o comando terminar com status de erro. Testes cobrem falha de remoção nos dois comandos.
**Prioridade de Correção**: ✅ Resolvido

### SYNC-001 - Resolver conflito não aplicava campo vazio sobre valor nulo
**Arquivo**: `app/Sync/ConflictResolver.php` (`camposAlterados`)
**Severidade**: 🟡 MÉDIO
**Categoria**: Lógica / Sincronização
**Status**: Corrigido nesta rodada.
**Descrição**: Ao escolher manter o valor do notebook, o resolvedor comparava os valores convertendo-os para string. Assim, `null` e `""` eram considerados iguais e o campo não era incluído na atualização.
**Risco/Impacto**: O conflito podia ser marcado como resolvido sem gravar a escolha feita; o valor antigo permanecia no servidor e seria reenviado aos notebooks.
**Recomendação**: Comparar `null` separadamente de string vazia, preservando a equivalência de booleanos e números usada pela aplicação. Coberto por `test_manter_valor_vazio_do_notebook_substitui_valor_nulo`.
**Padrão Recorrente**: Sim — alterações de sincronização devem distinguir ausência/nulo de texto vazio ao identificar campos modificados.
**Prioridade de Correção**: ✅ Resolvido

### SYNC-002 - Comparação de sincronização confundia nulo com valores textuais
**Arquivo**: `app/Sync/SyncValue.php` e comparadores de `app/Sync/`
**Severidade**: 🟡 MÉDIO
**Categoria**: Lógica / Sincronização
**Status**: Corrigido nesta rodada.
**Descrição**: Comparadores convertiam valores para texto e usavam a palavra `null` como marcador do valor nulo; a aplicação no notebook também convertia `null` e texto vazio para a mesma string. Isso ocultava mudanças como `NULL` para `"null"` ao resolver conflitos, podia deixar de detectar conflitos no servidor e impedia a aplicação de `NULL` para `""` no notebook.
**Risco/Impacto**: Escolhas de conflito podiam ser marcadas como resolvidas sem aplicar o valor selecionado, alterações concorrentes podiam sobrescrever dados sem conflito e notebooks podiam divergir do servidor.
**Recomendação**: Comparar `null` antes de normalizar escalares e reutilizar a mesma regra nos fluxos de envio, resolução e recebimento. Coberto por `ConflitosTest`, `SyncPushTest` e `SyncPullTest`.
**Padrão Recorrente**: Sim — os três caminhos de sincronização precisam preservar a distinção entre ausência, nulo e texto.
**Prioridade de Correção**: ✅ Resolvido

### AUTH-003 - Login aceitava tentativas ilimitadas de senha
**Arquivo**: `routes/web.php` (`migration.login.store`)
**Severidade**: 🟠 ALTO
**Categoria**: Segurança (autenticação)
**Status**: Corrigido nesta rodada.
**Descrição**: A rota de login não aplicava limite de tentativas, embora o formulário de recuperação de senha já limitasse solicitações a cinco por minuto.
**Risco/Impacto**: Um endereço podia enviar tentativas de senha sem restrição na aplicação.
**Correção**: Aplicar `throttle:5,1` à rota de login, com teste de regressão que confirma o HTTP 429 quando o limite já foi atingido.
**Prioridade de Correção**: ✅ Resolvido

### ADM-001 - Cadastro de administração podia ficar parcialmente salvo
**Arquivos**: `app/Services/LegacyAdministrationManagementService.php` e `tests/Feature/LegacyAdministrationMutationAtomicityTest.php`
**Severidade**: 🟡 MÉDIO
**Categoria**: Estabilidade / Integridade de dados
**Status**: Corrigido nesta rodada.
**Descrição**: Criar ou editar uma administração salvava primeiro o cadastro e depois sincronizava as administrações permitidas dos usuários administradores. Se a sincronização falhasse, a operação retornava erro, mas o cadastro já permanecia gravado.
**Risco/Impacto**: A tela indicava falha mesmo que a administração tivesse sido criada ou alterada, deixando o estado parcialmente aplicado.
**Correção**: Executar a gravação e a sincronização na mesma transação. Os testes provocam falha na segunda gravação e confirmam rollback tanto na criação quanto na edição.
**Prioridade de Correção**: ✅ Resolvido

### IMP-025 - CSV temporário de conversão permanecia após rejeição do upload
**Arquivo**: `app/Http/Controllers/SpreadsheetImportController.php` (`store`)
**Severidade**: 🟡 MÉDIO
**Categoria**: Importação de planilhas / Limpeza de arquivos
**Status**: Corrigido nesta rodada.
**Descrição**: Arquivos XLS/XLSX eram convertidos para um CSV temporário antes de validar a administração e o responsável. Se o serviço rejeitasse esses dados antes de mover o arquivo, o CSV convertido ficava no diretório temporário do PHP.
**Risco/Impacto**: Envios inválidos repetidos acumulavam cópias completas de planilhas no disco do servidor.
**Correção**: Remover o CSV convertido em um bloco `finally` após a tentativa de importação. Quando o caminho feliz move o arquivo para o armazenamento da importação, a limpeza não afeta a cópia persistida.
**Prioridade de Correção**: ✅ Resolvido

### PROD-001 - Produto aceitava tipo de bem de outra administração
**Arquivos**: `app/Services/LegacyProductManagementService.php` e `app/Services/ResolvesLegacyProductScope.php`
**Severidade**: 🟡 MÉDIO
**Categoria**: Regra de negócio / Integridade de dados
**Status**: Corrigido nesta rodada.
**Descrição**: A criação e a edição verificavam se o usuário podia acessar a igreja e o tipo de bem, mas não se o tipo pertencia à administração da igreja. Um administrador com acesso global podia associar cadastros de administrações diferentes.
**Risco/Impacto**: Produtos podiam ser registrados com classificação de outra administração, afetando a consistência dos cadastros e relatórios.
**Correção**: Validar também a administração da igreja ao resolver o tipo de bem, mantendo tipos compartilhados e igrejas sem administração identificada. Testes cobrem criação e edição com usuário global.
**Prioridade de Correção**: ✅ Resolvido

### HTTP-001 - Identificadores em array eram convertidos para o registro 1
**Arquivos**: `app/Support/QueryParameter.php` e controladores de igrejas, produtos, relatórios, transferências, importações e rotas legadas
**Severidade**: 🟠 ALTO
**Categoria**: Integridade de dados / Entrada HTTP
**Status**: Corrigido nesta rodada.
**Descrição**: Alguns endpoints convertiam diretamente valores enviados pelo usuário para inteiro. Em PHP, um array não vazio convertido dessa forma vira `1`; por exemplo, `comum_id[]=7` podia selecionar a igreja de ID 1. Isso também fazia rotas legadas encaminharem um ID diferente do informado.
**Risco/Impacto**: Uma requisição malformada podia consultar ou alterar dados de outro registro; no endpoint de exclusão, podia apagar os produtos da igreja de ID 1.
**Correção**: Centralizar a conversão de parâmetros numéricos em `QueryParameter`, que usa o valor padrão para entradas não escalares sem alterar a conversão dos valores escalares, e substituir os casts diretos encontrados nos controladores afetados. Testes de regressão cobrem rotas legadas, exclusão de produtos e progresso de importação.
**Prioridade de Correção**: ✅ Resolvido

### AUTH-005 - Login atribuía uma igreja global a usuário sem escopo
**Arquivo**: `app/Services/LegacyAuthSessionService.php` (`resolveInitialChurchId`)
**Severidade**: 🟠 ALTO
**Categoria**: Segurança (escopo de acesso) / integridade de dados
**Status**: Corrigido nesta rodada.
**Descrição**: Se um usuário ativo não administrador não tivesse igreja válida nem administração definida, o login selecionava a primeira igreja globalmente e gravava o ID no cadastro. Uma referência a igreja removida causava o mesmo fallback.
**Risco/Impacto**: Uma conta sem escopo passava a carregar o contexto de uma igreja alheia, e o login alterava o cadastro do usuário sem uma atribuição explícita.
**Correção**: Retornar escopo de igreja nulo quando a igreja atribuída estiver ausente ou inválida, sem selecionar outra igreja nem gravar no usuário. Testes de regressão cobrem conta sem igreja e referência a igreja inexistente.
**Prioridade de Correção**: ✅ Resolvido

### AUTH-006 - Usuário de administração não conseguia trocar de igreja
**Arquivo**: `app/Services/LegacyAuthSessionService.php` (`switchChurch`)
**Severidade**: 🟡 MÉDIO
**Categoria**: Regra de acesso / funcionalidade
**Status**: Corrigido nesta rodada.
**Descrição**: Contas comuns vinculadas a uma administração usam `comum_id` nulo e recebem igrejas pelo escopo de `administracoes_permitidas`. A troca de contexto ignorava esse escopo e comparava apenas `comum_id`, recusando todas as igrejas dessas contas.
**Risco/Impacto**: Usuários autorizados a várias administrações não conseguiam selecionar a igreja necessária para operar.
**Correção**: Permitir a troca quando a administração da igreja estiver no escopo do usuário; manter a restrição à igreja atribuída para contas legadas sem escopo de administração. Testes cobrem troca permitida e rejeição fora do escopo.
**Prioridade de Correção**: ✅ Resolvido

### PUB-001 - Acesso público listava igrejas fora da seleção
**Arquivos**: `app/Services/LegacyChurchBrowserService.php` e `tests/Unit/Services/LegacyChurchBrowserServiceTest.php`
**Severidade**: 🟡 MÉDIO
**Categoria**: Privacidade / escopo de consulta
**Status**: Corrigido nesta rodada.
**Descrição**: A seleção pública guardava a igreja na sessão e redirecionava para a listagem, mas a consulta não usava esse contexto. A página podia exibir dados de todas as igrejas e opções de administrações alheias à selecionada.
**Risco/Impacto**: Pessoas atendidas publicamente podiam consultar metadados de outras igrejas sem terem selecionado esse escopo.
**Correção**: Restringir a listagem, contagem e opções de administração à igreja da sessão pública, inclusive quando o ID vier da chave legada `public_planilha_id`. Teste cobre isolamento entre duas igrejas.
**Prioridade de Correção**: ✅ Resolvido

### IMP-026 - Aspas CSV após barra invertida eram exportadas com escape incompatível
**Arquivos**: `app/Http/Controllers/SpreadsheetImportController.php`, `app/Services/LegacyAuditTrailService.php` e `app/Services/LegacyReportService.php`
**Severidade**: 🟡 MÉDIO
**Categoria**: Importação e exportação de planilhas
**Status**: Corrigido nesta rodada.
**Descrição**: Os exportadores usavam o escape padrão de `fputcsv` (`\\`). Quando um campo continha barra invertida imediatamente antes de aspas, o CSV deixava de seguir o escape padrão e era lido com aspas e colunas incorretas pelo próprio leitor do sistema.
**Risco/Impacto**: Arquivos de correção, auditoria e relatórios podiam perder ou corromper valores ao serem abertos ou reimportados.
**Correção**: Configurar todos os exportadores para duplicar aspas e não usar escape proprietário, mantendo os separadores existentes. Testes de round-trip cobrem exportação de erros, auditoria e relatório.
**Prioridade de Correção**: ✅ Resolvido

### SCOPE-001 - Sessão autenticada sem administração listava dados globais
**Arquivos**: `app/Services/LegacyChurchBrowserService.php`, `app/Services/LegacyDepartmentBrowserService.php`, `app/Services/LegacyProductBrowserService.php` e `app/Services/LegacyReportService.php`
**Severidade**: 🟠 ALTO
**Categoria**: Segurança / isolamento entre administrações
**Status**: Corrigido nesta rodada.
**Descrição**: Os quatro serviços interpretavam a ausência simultânea dos campos de escopo como acesso global, mesmo quando `usuario_id` identificava uma sessão autenticada. Isso podia ocorrer com sessões antigas ou incompletas.
**Risco/Impacto**: Um usuário comum podia consultar igrejas, dependências, bens e opções de relatórios de administrações alheias.
**Correção**: Preservar o acesso global de administradores e de chamadas sem usuário autenticado; uma sessão de usuário comum sem administração passa a receber escopo vazio. Testes cobrem as listagens, contagens e opções nos quatro serviços.
**Prioridade de Correção**: ✅ Resolvido

### IMP-027 - Acentos em caixa alta faziam o parser pular o cabeçalho e aceitar metadados
**Arquivo**: `app/Services/CsvParserService.php` (`encontrarInicioDados`, `isLinhaMetadados`)
**Severidade**: 🟡 MÉDIO
**Categoria**: Importação de planilhas
**Status**: Corrigido nesta rodada.
**Descrição**: A detecção do cabeçalho convertia texto com `strtolower` e transliterava acentos por `iconv`; neste runtime, “Código” virava `c'odigo` e não era reconhecido. O fallback pulava mais uma linha e descartava o primeiro registro. A mesma normalização não reconhecia metadados em caixa alta com acentos, como “RELATÓRIO” e “DEPENDÊNCIA”.
**Risco/Impacto**: CSVs com cabeçalho de código perdiam silenciosamente o primeiro bem e linhas de metadados podiam virar registros importáveis.
**Correção**: Normalizar textos de comparação em minúsculas UTF-8 e remover pontuação residual da transliteração nos dois caminhos. Testes cobrem cabeçalho acentuado, metadados acentuados em caixa alta e a importação com arquivo CSV de fixture.
**Prioridade de Correção**: ✅ Resolvido

### TRANS-001 - Descrições longas impediam transferências de bens
**Arquivo**: `database/migrations/20261121110000_expand_transferencia_item_description.php`
**Severidade**: 🟡 MÉDIO
**Categoria**: Transferências / Integridade de dados
**Status**: Corrigido nesta rodada.
**Descrição**: A transferência juntava o nome do bem e seu complemento no histórico, mas a coluna aceitava no máximo 255 caracteres. Os dois campos podem ter até 255 caracteres cada, portanto uma descrição válida podia exceder a coluna e abortar a transferência em MySQL com modo estrito.
**Risco/Impacto**: Bens com descrição longa não podiam ser transferidos, apesar de seus dados serem aceitos pelo cadastro.
**Correção**: Ampliar a coluna do histórico para `TEXT` por migração incremental. O rollback verifica se há registros longos e interrompe a redução para evitar perda de dados. Testes cobrem o tipo da coluna e a preservação da descrição combinada acima de 255 caracteres.
**Prioridade de Correção**: ✅ Resolvido

### TRANS-002 - ID parcialmente numérico transferia o bem de outro identificador
**Arquivo**: `app/Services/LegacyTransferService.php` (`normalizarIds`)
**Severidade**: 🟡 MÉDIO
**Categoria**: Transferências / Integridade de dados
**Status**: Corrigido nesta rodada.
**Descrição**: A conversão direta de cada ID para inteiro aceitava um prefixo numérico. Por exemplo, `1001abc` era convertido em `1001` e podia transferir esse bem.
**Risco/Impacto**: Uma lista de IDs malformada podia mover um bem diferente do indicado pelo valor recebido pelo serviço.
**Correção**: Aceitar somente inteiros positivos ou strings compostas por dígitos; rejeitar a lista inteira antes da transação quando qualquer ID for inválido. Teste de regressão confirma que o bem não muda de igreja.
**Prioridade de Correção**: ✅ Resolvido

### SYNC-004 - Planilha sem permissão de leitura era sincronizada como arquivo vazio
**Arquivo**: `app/Sync/SpreadsheetStore.php` (`listar`, `ler`)
**Severidade**: 🟡 MÉDIO
**Categoria**: Lógica / sincronização de arquivos
**Status**: Corrigido nesta rodada.
**Descrição**: A listagem convertia falhas de `hash_file()` e `filesize()` em hash e tamanho vazios. A leitura também convertia `file_get_contents()` em falha para uma string vazia e calculava um hash válido desse conteúdo.
**Risco/Impacto**: Um notebook podia receber uma planilha vazia como se a transferência tivesse sido íntegra, enquanto a falha de permissão ficava oculta.
**Correção**: Interromper a listagem ou leitura quando o sistema não consegue acessar os dados e informar qual planilha falhou. Testes com arquivo sem permissão cobrem os dois caminhos.
**Prioridade de Correção**: ✅ Resolvido
