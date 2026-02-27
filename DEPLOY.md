# 🚀 Guia de Implantação RSNewsAuto 24/7

Este guia explica como transferir o sistema para o seu servidor e mantê-lo rodando continuamente.

## 1. Requisitos
- Node.js (v18 ou superior)
- NPM ou Yarn
- PM2 (para rodar 24/7)

## 2. Preparação do Servidor
No seu terminal, instale o PM2 globalmente:
```bash
npm install -g pm2
```

## 3. Transferência dos Arquivos
1. Baixe o código fonte do projeto.
2. No servidor, navegue até a pasta do projeto:
```bash
cd /caminho/para/rsnewsauto
```

## 4. Instalação e Build
Instale as dependências e gere a versão de produção:
```bash
npm install
npm run build
```

## 5. Configuração de Ambiente
Crie um arquivo `.env` na raiz do projeto com suas chaves:
```env
GEMINI_API_KEY=sua_chave_aqui
NODE_ENV=production
```

## 6. Rodando 24/7 com PM2
Para iniciar o servidor e garantir que ele reinicie sozinho se cair:
```bash
pm2 start server.ts --name rsnewsauto --interpreter tsx
```

Para salvar a lista de processos e iniciar junto com o sistema:
```bash
pm2 save
pm2 startup
```

## 7. Monitoramento
- Ver logs em tempo real: `pm2 logs rsnewsauto`
- Ver status: `pm2 status`
- Reiniciar: `pm2 restart rsnewsauto`

---
**Nota:** O sistema já está configurado para realizar a raspagem automática a cada 30 minutos. Você pode ajustar esse tempo no arquivo `server.ts`.
