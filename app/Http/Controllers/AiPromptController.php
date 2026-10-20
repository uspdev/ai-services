<?php

namespace App\Http\Controllers;

use App\Models\AiPrompt;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use UspTheme;

/**
 * Gerencia a listagem, edição, criação de versões derivadas e ativação de prompts no banco.
 *
 * Restrito a gerentes (`can:manager`). Não expõe criação livre de novos slugs nem exclusão
 * de registros, preservando o histórico de versões (`draft`, `active`, `archived`).
 */
class AiPromptController extends Controller
{
    /**
     * Marca o menu Settings -> Prompts de IA como ativo em todas as rotas deste controller.
     *
     * @return void
     */
    public function __construct()
    {
        UspTheme::activeUrl('settings/prompts');
    }
    /**
     * Lista todos os serviços de IA e suas respectivas versões de prompts agrupadas por `slug`.
     *
     * Caso a tabela `ai_prompts` ainda não possua as versões iniciais (`v1`), inicializa
     * os registros padrão de forma idempotente antes de renderizar a listagem.
     *
     * @return View Tela de gerenciamento de prompts e versões ativas.
     */
    public function index(): View
    {
        AiPrompt::ensureDefaultsExist();

        $promptsBySlug = AiPrompt::query()
            ->orderBy('slug')
            ->orderByDesc('version')
            ->get()
            ->groupBy('slug');

        $logsBySlug = [];
        foreach ($promptsBySlug->keys() as $slug) {
            $logsBySlug[(string) $slug] = AiPrompt::logsForSlug((string) $slug);
        }

        return view('prompts.index', [
            'promptsBySlug' => $promptsBySlug,
            'logsBySlug' => $logsBySlug,
        ]);
    }

    /**
     * Exibe o formulário de edição de um prompt com comparador Diff, restauração de padrão e metadados.
     *
     * @param AiPrompt $prompt Registro da versão de prompt selecionada para edição.
     * @return View Tela de edição da versão de prompt.
     */
    public function edit(AiPrompt $prompt): View
    {
        $activePrompt = AiPrompt::forSlug($prompt->slug)->active()->first() ?? $prompt;
        $nextVersion = ((int) AiPrompt::forSlug($prompt->slug)->max('version')) + 1;
        $metadataKey = AiPrompt::metadataListKey($prompt->slug);

        $currentMetadataItems = [];
        if ($metadataKey !== null && is_array($prompt->metadata[$metadataKey] ?? null)) {
            $currentMetadataItems = array_values($prompt->metadata[$metadataKey]);
        }

        return view('prompts.edit', [
            'prompt' => $prompt,
            'activePrompt' => $activePrompt,
            'nextVersion' => $nextVersion,
            'defaultTemplate' => AiPrompt::defaultTemplateForSlug($prompt->slug) ?? $prompt->system_prompt,
            'metadataKey' => $metadataKey,
            'currentMetadataItems' => implode("\n", $currentMetadataItems),
            'defaultMetadataItems' => implode("\n", AiPrompt::defaultMetadataItemsForSlug($prompt->slug)),
            'placeholders' => AiPrompt::placeholdersForSlug($prompt->slug),
            'logs' => AiPrompt::logsForSlug($prompt->slug),
        ]);
    }

    /**
     * Atualiza a versão atual ou cria uma nova versão derivada conforme a escolha do gerente.
     *
     * @param Request $request Requisição PUT contendo os campos do formulário e `save_mode`.
     * @param AiPrompt $prompt Versão de prompt base sendo editada.
     * @return RedirectResponse Redirecionamento para a listagem com mensagem de sucesso.
     * @throws \Illuminate\Validation\ValidationException Quando os dados enviados forem inválidos.
     */
    public function update(Request $request, AiPrompt $prompt): RedirectResponse
    {
        $metadataKey = AiPrompt::metadataListKey($prompt->slug);

        $rules = [
            'nome' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string', 'max:1000'],
            'system_prompt' => ['required', 'string'],
            'changelog' => ['nullable', 'string', 'max:1000'],
            'save_mode' => ['required', 'string', Rule::in(['update', 'new_version'])],
            'activate_now' => ['nullable', 'boolean'],
        ];

        if ($metadataKey !== null) {
            $rules['metadata_items'] = ['required', 'string', 'max:5000'];
        }

        $validated = $request->validate($rules);

        $metadata = is_array($prompt->metadata) ? $prompt->metadata : [];
        if ($metadataKey !== null) {
            $lines = preg_split('/\r\n|\r|\n/', (string) ($validated['metadata_items'] ?? '')) ?: [];
            $items = array_values(array_filter(array_map('trim', $lines), static fn(string $line): bool => $line !== ''));
            $metadata[$metadataKey] = $items;
        }

        // Identifica os campos alterados em relação à versão base para registrar no log de auditoria.
        $camposAlterados = [];
        if ($prompt->nome !== $validated['nome']) {
            $camposAlterados[] = 'nome';
        }
        if (($prompt->descricao ?? '') !== ($validated['descricao'] ?? '')) {
            $camposAlterados[] = 'descrição';
        }
        if ($prompt->system_prompt !== $validated['system_prompt']) {
            $camposAlterados[] = 'system_prompt';
        }
        if ($metadataKey !== null && ($prompt->metadata[$metadataKey] ?? []) !== ($metadata[$metadataKey] ?? [])) {
            $camposAlterados[] = "metadata.{$metadataKey}";
        }

        $resumoCampos = ! empty($camposAlterados)
            ? 'Campos alterados: ' . implode(', ', $camposAlterados) . '.'
            : 'Sem alteração nos campos de conteúdo.';
        $notaChangelog = ! empty($validated['changelog'])
            ? " Nota: {$validated['changelog']}"
            : '';

        if ($validated['save_mode'] === 'new_version') {
            $target = $prompt->createNextDraft($validated['changelog'] ?? null);
            $target->update([
                'nome' => $validated['nome'],
                'descricao' => $validated['descricao'] ?? null,
                'system_prompt' => $validated['system_prompt'],
                'changelog' => $validated['changelog'] ?? null,
                'metadata' => $metadata ?: null,
            ]);
            $acaoCodigo = 'new_version';
            $acaoRotulo = 'Nova versão criada';
            $detalheBase = "Criada a versão v{$target->version} a partir da v{$prompt->version}. {$resumoCampos}{$notaChangelog}";
            $mensagem = "Nova versão v{$target->version} do prompt '{$target->nome}' criada com sucesso.";
        } else {
            $prompt->update([
                'nome' => $validated['nome'],
                'descricao' => $validated['descricao'] ?? null,
                'system_prompt' => $validated['system_prompt'],
                'changelog' => $validated['changelog'] ?? null,
                'metadata' => $metadata ?: null,
            ]);
            $target = $prompt;
            $acaoCodigo = 'update';
            $acaoRotulo = 'Versão atualizada';
            $detalheBase = "Atualizada a versão v{$target->version}. {$resumoCampos}{$notaChangelog}";
            $mensagem = "Versão v{$target->version} do prompt '{$target->nome}' atualizada com sucesso.";
        }

        if ($request->boolean('activate_now')) {
            $target->activate();
            $detalheBase .= ' Ativada imediatamente em produção.';
            $mensagem .= " A versão v{$target->version} está ativa em produção.";
        } else {
            AiPrompt::forgetCache($target->slug);
        }

        $target->recordLog($acaoCodigo, $acaoRotulo, $detalheBase, $request->user());

        $request->session()->flash('alert-success', $mensagem);

        return redirect()->route('prompts.index');
    }

    /**
     * Promove uma versão de prompt para `active` (arquivando a anterior) e invalida o cache.
     *
     * @param Request $request Requisição PATCH de ativação.
     * @param AiPrompt $prompt Versão de prompt a ser ativada em produção.
     * @return RedirectResponse Redirecionamento para a listagem com confirmação.
     */
    public function activate(Request $request, AiPrompt $prompt): RedirectResponse
    {
        $prompt->activate();
        $prompt->recordLog(
            'activate',
            'Versão ativada',
            "Promoveu a versão v{$prompt->version} para ativa em produção.",
            $request->user()
        );

        $request->session()->flash(
            'alert-success',
            "Versão v{$prompt->version} do prompt '{$prompt->nome}' ativada em produção."
        );

        return redirect()->route('prompts.index');
    }
}
