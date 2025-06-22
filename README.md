# FTP-2.0

FTP-2.0 é um sistema de gerenciamento de arquivos via FTP, desenvolvido em PHP, que visa facilitar o acesso, manipulação e administração de arquivos em servidores remotos de maneira simples e eficiente, diretamente do navegador.

## Funcionalidades Principais

- **Conexão com múltiplos servidores FTP:** Permite cadastrar, editar e remover conexões a diferentes servidores FTP.
- **Upload e download de arquivos:** Faça upload de arquivos locais para o servidor e baixe arquivos do servidor para sua máquina.
- **Gerenciamento de diretórios:** Crie, renomeie e exclua pastas e arquivos.
- **Permissões de arquivos:** Visualize e altere permissões (chmod) de arquivos e diretórios.
- **Visualização de arquivos:** Visualize arquivos de texto e imagens diretamente na interface web.
- **Renomear e mover arquivos:** Organize facilmente os arquivos com as funções de renomear e mover.
- **Exclusão segura:** Exclua arquivos ou pastas com confirmação para evitar perdas acidentais.
- **Interface responsiva:** Utilização prática em desktops, tablets e dispositivos móveis.
- **Painel de configurações:** Ajuste preferências, idiomas e temas da interface.
- **Logs de atividades:** Registro das operações realizadas para fins de auditoria e segurança.

## Tecnologias Utilizadas

- **PHP:** Linguagem principal do backend.
- **HTML5 e CSS3:** Estrutura e estilização da interface.
- **JavaScript:** Funcionalidades interativas e melhorias na experiência do usuário.
- **Bootstrap (opcional):** Utilizado para responsividade e layout moderno.
- **Bibliotecas FTP do PHP:** Para comunicação segura com servidores FTP.

## Pré-requisitos

- Servidor web com suporte a PHP 7.2 ou superior.
- Extensão FTP do PHP habilitada.
- Acesso à internet para uso externo ou localhost para testes locais.

## Instalação

1. **Clone o repositório:**
   ```bash
   git clone https://github.com/lele2030br/FTP-2.0.git
   ```
2. **Envie os arquivos para seu servidor web.**
3. **Configure as permissões das pastas, se necessário.**
4. **Acesse via navegador:**  
   ```
   http://seusite.com/FTP-2.0/
   ```
5. **Configure suas contas FTP** através da interface do sistema.

## Uso

- Adicione uma nova conexão FTP informando o host, usuário e senha.
- Navegue pelas pastas do servidor remoto.
- Utilize os botões de ação para upload, download, renomear, mover ou excluir arquivos e pastas.
- Personalize as configurações conforme sua preferência no painel administrativo.

## Contribuindo

Contribuições são bem-vindas! Para contribuir:

1. Faça um fork do projeto.
2. Crie uma branch: `git checkout -b minha-feature`
3. Faça suas alterações e commit: `git commit -m 'Minha nova feature'`
4. Envie para o GitHub: `git push origin minha-feature`
5. Abra um Pull Request.

## Licença

Este projeto está licenciado sob a Licença MIT - consulte o arquivo [LICENSE](LICENSE) para detalhes.

---

Desenvolvido por [lele2030br](https://github.com/lele2030br)
