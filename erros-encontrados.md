# Erros Encontrados - Check Planilha Imobilizado

## Resumo
- Críticos: 0
- Altos: 8
- Médios: 11
- Baixos: 0

## Erros por Categoria
### Segurança (2)
### Lógica (18)
### Performance (0)
### Code Smell (0)

## Detalhes dos Erros

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
