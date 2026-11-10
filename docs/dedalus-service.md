Documentação de Uso: API de Busca no Acervo ABCD USP

Esta documentação técnica apresenta as especificações de uso, parâmetros de consulta, diretrizes de segurança e exemplos práticos para integração com a API de busca no acervo da Agência de Bibliotecas e Coleções Digitais (ABCD) da Universidade de São Paulo (USP).

1. Visão Geral e Arquitetura Middleware

A API foi disponibilizada para fornecer uma interface unificada de consulta ao acervo bibliográfico. Conforme especificado na documentação do serviço, a API atua como um middleware para abstrair o acesso direto ao sistema Aleph.

Diretriz de Arquitetura: O acesso direto à base de dados do Aleph permanece restrito devido a questões de segurança e à elevada complexidade das rotinas de busca da aplicação legada. Além disso, a instituição encontra-se nas fases finais de um processo de seleção para migração de plataforma no futuro. O middleware garante que a interface de integração permaneça estável para os consumidores da API durante e após esse processo de transição.

2. Protocolo de Acesso, Endpoint e Regras de Rede

O acesso aos serviços de busca é realizado por meio de uma URL centralizada, com diretrizes específicas quanto aos métodos HTTP aceitos e às restrições de rede.

Endpoint Principal

O ponto de entrada para a realização de consultas no acervo é:

https://apis.abcd.usp.br/BuscaAcervo.php


Métodos HTTP Aceitos

Na versão atual disponibilizada para testes, a API aceita requisições enviadas por dois métodos:

* POST: Método preferencial e recomendado para todas as integrações.
* GET: Aceito temporariamente para facilitar os testes de consulta.

A equipe responsável indica que o suporte ao método GET será desativado após a fase de testes e validação das consultas. Dessa forma, todas as novas integrações devem priorizar obrigatoriamente o método POST.

Controle de Acesso e Segurança por IP

Durante o período inicial de testes, a API está liberada para acesso a partir de qualquer endereço IP. No entanto, em etapa posterior, o acesso será restrito exclusivamente à faixa de endereços IP da Universidade de São Paulo (USP).

3. Especificação dos Parâmetros de Consulta

A API aceita um conjunto de parâmetros para filtrar e paginar as pesquisas no acervo. A tabela a seguir descreve cada parâmetro suportado, sua função e as regras de preenchimento.

Parâmetro	Significado / Função	Regra de Preenchimento e Valores Aceitos
search	Texto principal da busca	Texto da busca a ser realizada (obrigatório para consulta).
page	Número da página a exibir	Número inteiro indicando a página de resultados (iniciando em 1).
epage	Número de itens por página	Número inteiro que especifica a quantidade de itens retornados por página.
options	Filtro de campo de busca	Deve ser único. Aceita: any, byAuthor, byTitle, bySubject, byIsbn, byIssb.
resourceType	Tipo de recurso bibliográfico	Aceita múltiplos valores separados por vírgulas. Aceita: books, articles, thesis, other.
library	Sigla da biblioteca	String com a sigla da unidade bibliotecária (exemplo: EESC).
author	Filtro complementar de autor(es)	Permite filtro por autor(es), aceitando valor único ou múltiplos autores separados por vírgulas.

Detalhamento das Regras de Seleção

1. Filtro Unívoco de Campo (options): O parâmetro options permite definir o campo no qual a busca será executada. A regra do serviço exige que apenas um filtro seja especificado por requisição (opção única), cobrindo busca geral (any), autor (byAuthor), título (byTitle), assunto (bySubject), ISBN (byIsbn) e ISSN/ISSB (byIssb).
2. Seleção Múltipla de Recursos (resourceType): Diferente do parâmetro de campo, o tipo de recurso permite combinar diferentes categorias na mesma consulta. Os materiais são divididos em livros (books), artigos (articles), teses (thesis) e outros materiais (other), podendo ser declarados juntos usando vírgulas (exemplo: books,articles).
3. Filtro Específico por Autor (author): Implementado como um ajuste rápido para permitir a busca por autores específicos combinada com a indicação do campo de pesquisa. Admite um ou mais nomes separados por vírgula (exemplo: author=BEER,JOHNSTON).

4. Exemplos Práticos de Requisição

A seguir são apresentados os cenários práticos de consulta no acervo com a formatação dos parâmetros aceitos pela API.

4.1 Consulta Geral no Acervo

Para pesquisar um termo de forma ampla em todo o acervo sem restrição de campo ou material:

https://apis.abcd.usp.br/BuscaAcervo.php?search=science


4.2 Busca Direcionada por Autor via options

Para executar a busca restringindo o termo especificamente ao campo de autor:

https://apis.abcd.usp.br/BuscaAcervo.php?search=guidorizzi&options=byAuthor


4.3 Filtragem por Múltiplos Tipos de Recurso

Para retornar apenas materiais categorizados como livros e artigos:

https://apis.abcd.usp.br/BuscaAcervo.php?search=guidorizzi&resourceType=books,articles


4.4 Filtragem por Outros Materiais e Livros

Para consultar outros recursos combinados com livros:

https://apis.abcd.usp.br/BuscaAcervo.php?search=science&resourceType=other,books


4.5 Restrição por Unidade Bibliotecária

Para filtrar os resultados pertencentes a uma biblioteca específica (por exemplo, a Biblioteca da EESC):

https://apis.abcd.usp.br/BuscaAcervo.php?search=science&library=EESC


4.6 Busca Combinada com Filtro Múltiplo de Autores

Para consultas refinadas combinando termo de busca, biblioteca, tipo de recurso, filtro por título e autores específicos:

* Consulta com autor único:

https://apis.abcd.usp.br/BuscaAcervo.php?search=resistencia%20dos%20materiais&library=EESC&resourceType=books&options=byTitle&author=BEER


* Consulta com múltiplos autores:

https://apis.abcd.usp.br/BuscaAcervo.php?search=resistencia%20dos%20materiais&library=EESC&resourceType=books&options=byTitle&author=BEER,JOHNSTON


A inclusão do parâmetro author permite refinar o resultado, reduzindo consideravelmente a quantidade de itens genéricos retornados.

5. Estrutura do Retorno JSON e Diretrizes de Consumo

O retorno das consultas realizadas na API é disponibilizado em formato JSON.

Tratamento de Itens Compartilhados entre Bibliotecas: Ao realizar pesquisas filtradas por uma biblioteca específica (usando o parâmetro library), a API retornará os itens correspondentes no acervo dessa unidade. Caso o item consultado também esteja disponível em outras bibliotecas da USP, a estrutura JSON listará essas demais unidades. As aplicações consumidoras podem ignorar essas outras unidades no JSON caso necessitem tratar apenas a biblioteca pesquisada.
