<form method="POST" action="{{ route('texto.index') }}">
  @csrf
  <div class="form-group">
    <label for="rascunho">
      Rascunho ou tópicos do texto
      <span class="badge badge-outline-info usptheme-contador-placeholder">
        <span class="usptheme-contador-paragrafos"></span>
        · <span class="usptheme-contador-palavras"></span>
        · <span class="usptheme-contador-caracteres"></span>
      </span>
    </label>
    {{-- Recupera o rascunho enviado no POST ao voltar após validação ou falha da IA. --}}
    <textarea id="rascunho" name="rascunho" class="form-control usptheme-contador" rows="6" maxlength="10000"
      placeholder="Digite aqui seu rascunho, tópicos ou ideias principais..." required>{{ old('rascunho', $rascunho ?? '') }}</textarea>
  </div>

  <div class="form-row align-items-end mb-2">
    <div class="col-md-4 mb-2 mb-md-0">
      <label for="style" class="font-weight-bold text-secondary mb-1">
        Estilo / Formato
      </label>
      <select name="style" id="style" class="custom-select custom-select-sm">
        @foreach ($styles as $s)
          <option value="{{ $s }}" {{ old('style', $style ?? '') === $s ? 'selected' : '' }}>
            {{ $s }}
          </option>
        @endforeach
      </select>
    </div>

    <div class="col-md-6 mb-2 mb-md-0">
      <label for="contexto" class="font-weight-bold text-secondary mb-1">
        Contexto ou destinatário <span class="font-weight-normal text-muted">(opcional)</span>
      </label>
      <input type="text" id="contexto" name="contexto" class="form-control form-control-sm" maxlength="1000"
        value="{{ old('contexto', $contexto ?? '') }}"
        placeholder="Ex.: Comunicado aos docentes sobre prazos de matrícula">
    </div>

    <div class="col-md-2">
      <button type="submit" class="btn btn-primary btn-sm btn-block btn-spinner">Processar</button>
    </div>
  </div>
</form>

