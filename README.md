# FTP 2.0

Um gerenciador de arquivos via FTP feito em PHP, moderno, responsivo, fácil de usar e com recursos avançados de gerenciamento de arquivos e diretórios.

## Recursos

- **Interface Moderna e Responsiva**: Visual limpo com Bootstrap 5, ícones, feedback visual e modo escuro.
- **Conexão Segura**: Suporte a conexões FTP e FTPS (SSL).
- **Upload/Download de Arquivos**: Múltiplas operações fáceis e rápidas.
- **Gerenciamento de Arquivos**:
  - Listagem de diretórios e arquivos com informações detalhadas.
  - Upload, download, exclusão, renomear, alterar permissões (chmod), editar arquivos de texto.
  - Criação de novos diretórios.
  - **Criação de novos arquivos vazios.**
- **Edição Online**: Edite arquivos de texto diretamente pelo navegador.
- **Barra de navegação (breadcrumbs)**: Navegação fácil entre diretórios.
- **Confirmação para ações perigosas**: Exclusão só com confirmação.
- **Modo Claro/Escuro**: Interface adaptável ao gosto do usuário.

## Como Usar

1. **Requisitos**
   - PHP 7.4 ou superior.
   - Extensão FTP habilitada no PHP.
   - Servidor web (Apache, Nginx, etc).

2. **Instalação**
   - Faça o download ou clone o repositório:
     ```sh
     git clone https://github.com/lele2030br/FTP-2.0.git
     ```
   - Coloque os arquivos em seu servidor web.

3. **Configuração**
   - Não é necessário configurar nada além do acesso ao PHP.
   - Certifique-se de que o servidor pode acessar servidores FTP externos.

4. **Utilização**
   - Acesse `index.php` pelo navegador.
   - Informe os dados do servidor FTP e conecte-se.
   - Gerencie arquivos e diretórios de forma visual.

## Segurança

- O sistema utiliza tokens CSRF para evitar ações não autorizadas.
- Use sempre HTTPS para acessar a interface web.
- Recomenda-se restringir o acesso por autenticação no servidor web (por exemplo, usando .htaccess).

## Customização

- Você pode alterar o visual editando o CSS no próprio arquivo ou adicionando temas Bootstrap.
- Para adicionar novos recursos, edite o arquivo `index.php`.

## Licença

MIT. Sinta-se livre para usar, modificar e compartilhar.

---

Feito com ❤️ por [lele2030br](https://github.com/lele2030br)