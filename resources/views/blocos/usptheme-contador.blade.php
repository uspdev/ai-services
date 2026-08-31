@pushOnce('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', () => {

      const seletores = {
        campo: '.usptheme-contador',
        container: '.form-group',
        placeholder: '.usptheme-contador-placeholder',
        paragrafos: '.usptheme-contador-paragrafos',
        palavras: '.usptheme-contador-palavras',
        caracteres: '.usptheme-contador-caracteres',
        separador: ' · ',
      };

      document.querySelectorAll(seletores.campo).forEach(campo => {
        const container = campo.closest(seletores.container);

        let placeholder = container?.querySelector(seletores.placeholder);

        const contadores = {
          paragrafos: container?.querySelector(seletores.paragrafos),
          palavras: container?.querySelector(seletores.palavras),
          caracteres: container?.querySelector(seletores.caracteres),
        };

        const possuiSubelementos = Object.values(contadores).some(Boolean);

        if (!possuiSubelementos) {
          if (!placeholder) {
            placeholder = document.createElement('span');
            placeholder.className =
              seletores.placeholder.replace('.', '') +
              ' text-muted small';

            campo.insertAdjacentElement('afterend', placeholder);
          }

          placeholder.innerHTML = `
            <span class="${seletores.paragrafos.replace('.', '')}"></span>
            ${seletores.separador}
            <span class="${seletores.palavras.replace('.', '')}"></span>
            ${seletores.separador}
            <span class="${seletores.caracteres.replace('.', '')}"></span>
          `;

          contadores.paragrafos = placeholder.querySelector(
            seletores.paragrafos
          );
          contadores.palavras = placeholder.querySelector(
            seletores.palavras
          );
          contadores.caracteres = placeholder.querySelector(
            seletores.caracteres
          );
        }

        function obterTexto(elemento) {
          return elemento.matches('textarea, input')
            ? elemento.value
            : elemento.innerText;
        }

        function atualizarContador() {
          const texto = obterTexto(campo);
          const textoTrim = texto.trim();

          const paragrafos = textoTrim
            ? texto.split(/\r?\n/).length
            : 0;

          const palavras = textoTrim
            ? textoTrim.split(/\s+/).length
            : 0;

          const caracteres = texto.length;

          if (contadores.paragrafos) {
            contadores.paragrafos.textContent =
              `${paragrafos} parágrafo(s)`;
          }

          if (contadores.palavras) {
            contadores.palavras.textContent =
              `${palavras} palavra(s)`;
          }

          if (contadores.caracteres) {
            contadores.caracteres.textContent =
              `${caracteres} caractere(s)`;
          }
        }

        campo.addEventListener('input', atualizarContador);
        atualizarContador();
      });

    });
  </script>
@endpushOnce
