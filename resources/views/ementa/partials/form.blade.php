<form method="POST" action="{{ route('bibliografia.ementa') }}">
  @csrf
  <div class="form-group">
    <label for="mensagem">
      Texto a ser avaliado
      <span class="badge badge-outline-info usptheme-contador-placeholder">
        <span class="usptheme-contador-paragrafos"></span>
        · <span class="usptheme-contador-palavras"></span>
        · <span class="usptheme-contador-caracteres"></span>
      </span>
    </label>
    <textarea id="mensagem" name="mensagem" class="form-control usptheme-contador" rows="6" maxlength="10000"
      placeholder="Cole aqui o texto..." required>{{ old('mensagem', $mensagem ?? '') }}</textarea>
  </div>

  <button type="submit" class="btn btn-primary btn-spinner">Processar</button>
</form>
