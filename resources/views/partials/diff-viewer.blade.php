@push('styles')
  <style>
    .diff-container {
      min-height: 280px;
      overflow: hidden;
      white-space: pre-wrap;
      word-break: break-word;
      line-height: 1.5;
      background-color: #e9ecef !important;
      border: 1px solid #ced4da;
      border-radius: 0.25rem;
      padding: 0.375rem 0.75rem;
    }

    del,
    .del {
      color: #dc3545 !important;
      text-decoration: line-through;
    }

    ins,
    .ins {
      color: #28a745 !important;
      text-decoration: none;
    }
  </style>
@endpush

<script src="https://cdn.jsdelivr.net/npm/diff@8.0.2/dist/diff.min.js"></script>

<script>
  window.UIDiffViewer = (() => {
    const escapeHtml = (text) => {
      return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    };

    const atualizar = (elOriginal, elDiff, elResposta) => {
      if (!elOriginal || !elDiff || !elResposta) return;

      const textoResposta = elResposta.innerText.trim();

      const changes = Diff.diffWordsWithSpace(
        elOriginal.value,
        textoResposta
      );

      elDiff.innerHTML = changes
        .map(change => {
          const text = escapeHtml(change.value);
          if (change.removed) return `<del class="del">${text}</del>`;
          if (change.added) return `<ins class="ins">${text}</ins>`;
          return text;
        })
        .join('');

      window.dispatchEvent(new CustomEvent('diff:updated'));
    };

    return {
      atualizar
    };
  })();
</script>
