<?php

namespace App\Enums;

/**
 * Representa os estados do ciclo de vida de uma versão de prompt no banco de dados.
 */
enum PromptStatus: string
{
    /** Versão futura em elaboração ou em fase de testes/homologação. */
    case Draft = 'draft';

    /** Versão oficial em produção para o respectivo serviço (slug). */
    case Active = 'active';

    /** Versão passada substituída e preservada para histórico e rollback. */
    case Archived = 'archived';

    /**
     * Retorna o rótulo legível em português correspondente ao status.
     *
     * @return string Descrição amigável do estado da versão.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho (Futura)',
            self::Active => 'Ativa (Produção)',
            self::Archived => 'Arquivada (Passada)',
        };
    }
}

