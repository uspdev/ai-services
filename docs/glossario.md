# 📖 GLOSSÁRIO DE INTELIGÊNCIA ARTIFICIAL: ENTENDA OS TERMOS
**Guia Prático**

### 🧠 LLM (Grande Modelo de Linguagem)
* **O que significa:** É o "cérebro" e a base de conversação do assistente virtual (exemplos: Gemini, GPT, Claude).
* **Analogia simples:** Pense nele como um **estudante extremamente inteligente e bom de papo**. Ele passou anos lendo bilhões de páginas de livros e internet, por isso sabe conversar sobre qualquer assunto de forma natural, escrever e-mails e tirar dúvidas gerais.

### 📚 RAG (Geração Aumentada de Recuperação)
* **O que significa:** É a técnica que permite à Inteligência Artificial consultar uma base de dados externa (como os manuais da nossa Intranet) antes de te dar uma resposta.
* **Analogia simples:** É uma **prova com consulta**. Sem o RAG, o assistente responde apenas com o que lembra "de cabeça" (o que pode gerar erros). Com o RAG, antes de responder, o assistente abre o livro oficial na página certa, lê a regra atualizada e redige a resposta para você com base no documento real.

### 🔌 MCP (Protocolo de Contexto de Modelo)
* **O que significa:** É um padrão de conexão que permite que diferentes sistemas de IA conversem e usem ferramentas de outros softwares de forma universal.
* **Analogia simples:** É a **"tomada USB-C" da Inteligência Artificial**. Antigamente, para conectar a IA ao sistema de agendamento de salas ou ao e-mail, era preciso criar uma fiação personalizada e complexa. O MCP padronizou essa conexão. Graças a ele, a IA pode não apenas ler informações, mas executar ações reais, como apertar o botão para reservar um anfiteatro para você.

### ✍️ Engenharia de Prompt
* **O que significa:** É a técnica de planejar, escrever e estruturar os comandos que enviamos para a IA para obter a melhor resposta possível.
* **Analogia simples:** É o ato de **dar uma instrução clara para um estagiário**. Se você pedir apenas *"faça um texto sobre ônibus"*, a resposta será genérica. Mas se usar engenharia de prompt e disser: *"Você é o secretário da Graduação. Escreva um e-mail de 3 parágrafos lembrando os docentes de enviar o termo de viagem com 15 dias de antecedência"*, o resultado será perfeito e sob medida.

### 🗺️ VectorDB (Banco de Dados Vetorial)
* **O que significa:** É o local onde os textos e manuais são guardados de uma forma que a IA consegue entender o *significado* das palavras, e não apenas letras soltas.
* **Analogia simples:** É um **organizador por contexto**. Em um banco de dados comum, se você buscar por "carro", ele não acha "automóvel". No VectorDB, as palavras viram coordenadas em um mapa conceitual. Ele entende que se um aluno busca por *"desconto para ônibus"*, o documento mais importante para mostrar é o de *"Passe Escolar"*, mesmo que as palavras exatas sejam diferentes.

### 🔄 Proxy de LLM (Roteador de Modelos)
* **O que significa:** É uma camada de software intermediária que unifica a comunicação com diferentes provedores de Inteligência Artificial (como Google, OpenAI e Anthropic) sob um único padrão de código.
* **O papel do LiteLLM como Proxy:** Ele funciona como um **adaptador universal**. No código do chatbot da EESC, você programa o robô usando apenas o padrão do LiteLLM. Se hoje o sistema usa o modelo *Gemini* (Google) e amanhã a universidade decidir trocar para o *Claude* (Anthropic), você não precisa reescrever nenhuma linha do código do chatbot. O LiteLLM faz a tradução dos comandos automaticamente nos bastidores.

### 🚪 AI Gateway (Portal e Governança de IA)
* **O que significa:** É uma camada de infraestrutura focada em segurança, controle financeiro, monitoramento e aplicação de políticas de uso (*fencing*) sobre o tráfego de dados entre os usuários e as IAs.
* **O papel do LiteLLM como Gateway:** Ele atua como o **guarda de trânsito e segurança do sistema**. Além de traduzir o código, o LiteLLM possui recursos integrados para gerenciar chaves de acesso (API Keys), controlar o orçamento limitando quantos *tokens* cada setor da EESC pode gastar (*rate limiting*) e aplicar barreiras de proteção (*fencing*). Ele intercepta as mensagens para apagar dados sensíveis de alunos (como CPF ou Número USP) antes que saiam da rede da universidade e bloqueia perguntas ofensivas ou fora do escopo institucional.

### 🎛️ UI/UX de IA (Interface e Experiência do Usuário)
* **O que significa:** É o design visual e a forma como o usuário interage com o chatbot na tela (caixas de texto, botões de clique rápido, avatares e animações).
* **Analogia simples:** É o **painel e o volante de um carro**. Não importa o quão potente seja o motor (o LLM), se o painel for confuso, o motorista não consegue dirigir. No chatbot da EESC, uma boa interface inclui sugerir perguntas prontas (ex: *"Como peço passe de ônibus?"*) para poupar o usuário de ter que adivinhar o que digitar.

### 🪙 Token
* **O que significa:** É a unidade de medida que os modelos de IA usam para processar o texto. Um token não é necessariamente uma palavra inteira; geralmente corresponde a um pedaço de palavra (cerca de 4 caracteres).
* **Analogia simples:** É a **"fração de página" ou o taxímetro da IA**. Os provedores de inteligência artificial não cobram por letra ou por pergunta, mas sim pela quantidade de tokens que a IA lê e escreve. Gerenciar bem os tokens no manual da EESC evita que o sistema gaste o orçamento desnecessariamente com respostas longas demais.

### 🛑 Guardrails (Trilhos de Segurança)
* **O que significa:** São regras e filtros rígidos de programação que impedem a IA de desviar do seu comportamento esperado, garantindo respostas éticas, seguras e polidas.
* **Analogia simples:** São as **defesas metálicas (guardrails) na lateral de uma estrada**. Se o motorista perder o controle, a barreira impede o carro de cair no abismo. No nosso chatbot, os guardrails garantem que se alguém fizer uma pergunta ofensiva, política ou fora do escopo da USP, a IA responda educadamente: *"Desculpe, sou um assistente focado em serviços administrativos da EESC e não posso ajudar com esse assunto"*.

### 📊 Observabilidade de IA (LLM Observability)
* **O que significa:** É o ato de monitorar, rastrear e analisar em tempo real a performance, os erros, as respostas e os custos de todo o sistema de IA.
* **Analogia simples:** É o **painel de telemetria de um avião ou o histórico de exames de um paciente**. Através de ferramentas de observabilidade, a equipe técnica da EESC consegue ver quais perguntas os alunos estão fazendo mais, onde a IA está demorando para responder, quais respostas foram avaliadas como ruins pelos usuários e se há alguma tentativa de ataque ou uso indevido do sistema.

### 📊 Observabilidade de IA no LiteLLM
* **O que significa:** É a capacidade de monitorar, rastrear, auditar e analisar em tempo real tudo o que acontece no ecossistema de Inteligência Artificial do chatbot (custos, erros, velocidade e qualidade das respostas).
* **Como o LiteLLM resolve isso:** Ele funciona como a **caixa-preta de um avião**. No arquivo de configuração do LiteLLM (`config.yaml`), basta ativar uma linha de comando (chamada de *success_callback*) para que ele passe a registrar e exportar automaticamente todos os dados de uso para plataformas de monitoramento (como Langfuse, Helicone, OpenTelemetry ou Prometheus). Isso permite que a equipe de TI da EESC descubra quais perguntas os alunos fazem mais, quais respostas foram ruins e quanto cada setor está gastando em créditos de IA.

### 🔒 Context Window (Janela de Contexto)
* **O que significa:** É o limite máximo de texto que o modelo de IA consegue "ler" e manter na memória de curto prazo em uma única conversa.
* **Analogia simples:** É a **capacidade de memória de trabalho da IA** durante uma reunião. Se a janela de contexto for pequena e você colar um documento de 50 páginas, a IA vai esquecer o que estava escrito no começo do texto enquanto lê o final. Os modelos modernos possuem janelas gigantes, permitindo ler manuais inteiros de uma só vez.
