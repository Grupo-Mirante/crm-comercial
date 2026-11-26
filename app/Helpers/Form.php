<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Campos de formulário padronizados: rótulo, asterisco vermelho nos obrigatórios, máscara, ajuda e
 * a mensagem de erro do servidor logo abaixo do campo (explicando como corrigir).
 * Os valores antigos (após erro de validação) têm prioridade sobre o valor do registro.
 */
final class Form
{
    /** @param array<string,mixed> $o opções: req, mask, type, help, col, attrs, placeholder, readonly, maxlength, id */
    public static function input(string $nome, string $rotulo, mixed $valor = '', array $o = []): string
    {
        $id = $o['id'] ?? 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $nome);
        $v = old($nome, $valor ?? '');
        $type = $o['type'] ?? 'text';
        $attrs = [
            'class' => 'form-control' . tem_erro($nome) . (isset($o['class']) ? ' ' . $o['class'] : ''),
            'id' => $id, 'name' => $nome, 'type' => $type, 'value' => is_scalar($v) ? (string) $v : '',
        ];
        if (!empty($o['req'])) {
            $attrs['required'] = true;
        }
        if (!empty($o['mask'])) {
            $attrs['data-mask'] = $o['mask'];
            $attrs['inputmode'] = in_array($o['mask'], ['cep', 'telefone', 'moeda', 'percentual', 'inteiro', 'data'], true) ? ($o['mask'] === 'moeda' || $o['mask'] === 'percentual' ? 'decimal' : 'numeric') : null;
        }
        foreach (['placeholder', 'maxlength', 'min', 'max', 'step', 'autocomplete', 'pattern'] as $k) {
            if (isset($o[$k])) {
                $attrs[$k] = (string) $o[$k];
            }
        }
        if (!empty($o['readonly'])) {
            $attrs['readonly'] = true;
        }
        if (!empty($o['disabled'])) {
            $attrs['disabled'] = true;
        }
        foreach (($o['attrs'] ?? []) as $k => $val) {
            $attrs[$k] = $val;
        }
        $html = '<input' . self::attrs($attrs) . '>';
        return self::wrap($id, $nome, $rotulo, $html, $o);
    }

    /** @param array<int|string,string> $opcoes valor => rótulo (lista simples usa o próprio texto) @param array<string,mixed> $o */
    public static function select(string $nome, string $rotulo, array $opcoes, mixed $valor = '', array $o = []): string
    {
        $id = $o['id'] ?? 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $nome);
        $v = (string) old($nome, $valor ?? '');
        $lista = array_is_list($opcoes) ? array_combine($opcoes, $opcoes) : $opcoes;
        $html = '<select' . self::attrs(['class' => 'form-select' . tem_erro($nome), 'id' => $id, 'name' => $nome, 'required' => !empty($o['req']), 'disabled' => !empty($o['disabled'])] + ($o['attrs'] ?? [])) . '>';
        if (($o['vazio'] ?? true) !== false) {
            $html .= '<option value="">' . e($o['vazio'] ?? '— escolha —') . '</option>';
        }
        foreach ($lista as $k => $rot) {
            $html .= '<option value="' . e($k) . '"' . ((string) $k === $v ? ' selected' : '') . '>' . e($rot) . '</option>';
        }
        $html .= '</select>';
        return self::wrap($id, $nome, $rotulo, $html, $o);
    }

    /** @param array<string,mixed> $o */
    public static function textarea(string $nome, string $rotulo, mixed $valor = '', array $o = []): string
    {
        $id = $o['id'] ?? 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $nome);
        $v = old($nome, $valor ?? '');
        $html = '<textarea' . self::attrs(['class' => 'form-control' . tem_erro($nome), 'id' => $id, 'name' => $nome, 'rows' => (string) ($o['rows'] ?? 3), 'required' => !empty($o['req']), 'maxlength' => isset($o['maxlength']) ? (string) $o['maxlength'] : null, 'readonly' => !empty($o['readonly'])]) . '>' . e(is_scalar($v) ? $v : '') . '</textarea>';
        return self::wrap($id, $nome, $rotulo, $html, $o);
    }

    /** @param array<string,mixed> $o */
    public static function check(string $nome, string $rotulo, bool $marcado, array $o = []): string
    {
        $id = $o['id'] ?? 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $nome);
        $old = $GLOBALS['__old'] ?? [];
        $m = $old ? !empty($old[$nome]) : $marcado;
        $col = $o['col'] ?? 'col-12';
        return '<div class="' . e($col) . '"><input type="hidden" name="' . e($nome) . '" value="0"><div class="form-check form-switch mt-1">'
            . '<input class="form-check-input" type="checkbox" role="switch" id="' . e($id) . '" name="' . e($nome) . '" value="1"' . ($m ? ' checked' : '') . '>'
            . '<label class="form-check-label" for="' . e($id) . '">' . e($rotulo) . '</label>'
            . (isset($o['help']) ? '<div class="form-text">' . e($o['help']) . '</div>' : '') . '</div></div>';
    }

    /** @param array<string,mixed> $o */
    private static function wrap(string $id, string $nome, string $rotulo, string $campo, array $o): string
    {
        $col = $o['col'] ?? 'col-md-6';
        $req = !empty($o['req']) ? ' <span class="req" aria-hidden="true">*</span><span class="visually-hidden">(obrigatório)</span>' : '';
        $help = isset($o['help']) ? '<div class="form-text">' . e($o['help']) . '</div>' : '';
        $grupo = isset($o['prefixo']) || isset($o['sufixo'])
            ? '<div class="input-group">' . (isset($o['prefixo']) ? '<span class="input-group-text">' . e($o['prefixo']) . '</span>' : '') . $campo . (isset($o['sufixo']) ? '<span class="input-group-text">' . e($o['sufixo']) . '</span>' : '') . '</div>'
            : $campo;
        return '<div class="' . e($col) . '"><label class="form-label" for="' . e($id) . '">' . e($rotulo) . $req . '</label>' . $grupo . erro($nome) . $help . '</div>';
    }

    /** @param array<string,mixed> $a */
    private static function attrs(array $a): string
    {
        $s = '';
        foreach ($a as $k => $v) {
            if ($v === null || $v === false) {
                continue;
            }
            $s .= $v === true ? ' ' . e($k) : ' ' . e($k) . '="' . e($v) . '"';
        }
        return $s;
    }

    /** Lista de UFs (do Gerador de PI, mais as demais). @return list<string> */
    public static function ufs(): array
    {
        return ['MA', 'AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MG', 'MS', 'MT', 'PA', 'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO'];
    }

    /** Valor monetário para exibir em campo com máscara (1234.5 → "1.234,50"). */
    public static function moeda(mixed $v): string
    {
        return $v === null || $v === '' ? '' : number_format((float) $v, 2, ',', '.');
    }
}
