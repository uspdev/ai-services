<form method="POST" action="{{ route('bibliografia.processar') }}">
  @csrf
  <div class="form-group">
    <label for="referencias">
      Referências bibliográficas
      <span class="badge badge-outline-info usptheme-contador-placeholder">
        <span class="usptheme-contador-paragrafos"></span>
        · <span class="usptheme-contador-palavras"></span>
        · <span class="usptheme-contador-caracteres"></span>
      </span>
    </label>
    <textarea id="referencias" name="referencias" class="form-control usptheme-contador" rows="6" maxlength="10000"
      placeholder="Cole aqui as referências bibliográficas..." required>{{ old('referencias', $referencias ?? '') }}</textarea>
  </div>

  <button type="submit" class="btn btn-primary btn-spinner">Processar</button>
</form>
