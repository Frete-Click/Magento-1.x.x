# Frete Click - Magento-1.x.x
Módulo para realização de frete com a Frete Click na plataforma Magento 1.x.x!

## 1º Passo: pré-configuração do Github Magento 1
- Após realizar o Download do nosso Github, localize o arquivo em seu computador. Em seguida, é necessário descompactar o arquivo baixado e copiar a pasta app para a pasta principal (raiz) da sua loja Magento.

-  Acesse a área administrativa de sua loja Magento e limpe o cache para que o módulo seja instalado e reconhecido. Para isso, basta acessar o menu System, clicar em Cache Maganement e em seguida, no botão “LIMPAR CACHE”

## 2º Passo: encontrar o TOKEN  na plataforma Cota Fácil - Frete Click
- Crie uma conta em nosso painel. https://cotafacil.freteclick.com.br
- Use a tela System->Sales->Shipping Methods->Frete Click  para configurar o plug-in e inserir a chave de API.

## 3º Passo: configuração na plataforma Magento 1

1º Crie os seguites attributos em Catalog->Attributes->Manage Attributes
- Altura				= Height
- Largura				= Width
- Comprimento			= Length
- Diferença de Encaixe	= Fit Difference
- Prazo de Postagem		= Posting Deadline

2º Configure o Fator de tamanho e peso.
- Fator de Tamanho  = 100
- Fator de Peso     = 1000