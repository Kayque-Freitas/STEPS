# 🧪 Guia de Testes - Otimizações Mobile

## Teste Rápido de Responsividade

### 1. Login Page
**URL:** `http://localhost/STEPS/login.php`

#### Desktop (1920px)
- [ ] Logo e título visíveis
- [ ] Formulário centrado
- [ ] Inputs têm 100px de largura mínima
- [ ] Botão "Entrar" é visível
- [ ] Hover effects funcionam

#### Tablet (768px)
- [ ] Formulário ainda centrado
- [ ] Inputs ocupam mais espaço
- [ ] Tudo legível sem scroll

#### Celular (375px - iPhone)
- [ ] Logo reduzido para 0.95rem
- [ ] Inputs ocupam 100% da largura
- [ ] Botão também full-width
- [ ] Sem scroll horizontal
- [ ] Padding reduzido para 1.5rem

---

### 2. Dashboard (Admin)
**URL:** `http://localhost/STEPS/index.php`
**Credenciais:** admin / admin123

#### Desktop (1920px)
- [ ] Sidebar vertical à esquerda com todos os textos
- [ ] Conteúdo ocupa 75% da tela
- [ ] Tabelas com todas as colunas visíveis
- [ ] Cards em grid 2 colunas

#### Tablet (768px)
- [ ] Sidebar muda para horizontal
- [ ] Ícones visíveis com labels
- [ ] Tabelas continuam acessíveis
- [ ] Cards em grid responsivo

#### Celular (375px)
- [ ] Sidebar com ícones apenas (labels ocultos)
- [ ] Cada aba ocupa 25% do espaço
- [ ] Tabelas com scroll horizontal
- [ ] Cards empilhados
- [ ] Botões de ação ocupam múltiplas linhas

---

### 3. Página de Vídeo
**URL:** `http://localhost/STEPS/watch.php?id=1`

#### Desktop
- [ ] Vídeo à esquerda (70%)
- [ ] Sidebar à direita com QR Code e relacionados
- [ ] Botões de compartilhamento lado a lado

#### Tablet
- [ ] Vídeo full-width
- [ ] QR Code e relacionados abaixo

#### Celular (375px)
- [ ] Vídeo full-width
- [ ] Informações com icones e badges
- [ ] **Botões de compartilhamento:**
  - [ ] Full-width
  - [ ] Empilhados
  - [ ] Cada um com ícone + texto completo
- [ ] QR Code centralizado
- [ ] Vídeos relacionados em cards com thumbnail (120px altura)

---

## ✨ Testes de Interatividade

### Touch Feedback
1. Abra em celular/tablet
2. Toque em qualquer botão
3. Observe:
   - [ ] Feedback visual (semi-transparência)
   - [ ] Sem delay de 300ms
   - [ ] Ação acontece imediatamente

### Inputs
1. Clique em qualquer input
2. Observe:
   - [ ] Teclado aparece
   - [ ] Input NOT zoomed (stay 1:1 scale)
   - [ ] Altura mínima respeitada (44px)
   - [ ] Foco com outline visível

### Formulários
1. Preencha e envie um formulário
2. Observe:
   - [ ] Botão fica desabilitado
   - [ ] Loading spinner aparece
   - [ ] Sem submissão dupla

---

## 📊 Performance em Mobile

### Métricas Esperadas
- **First Paint:** < 2s
- **Interactive:** < 3.5s
- **CLS (Layout Shift):** < 0.1
- **LCP (Largest Paint):** < 2.5s

### Ferramenta de Teste
Use Google Lighthouse:
1. DevTools → Lighthouse
2. Selecione "Mobile"
3. Run audit
4. Score mínimo esperado: 85+

---

## 🔍 Checklist Final

### Acessibilidade
- [ ] Todos os inputs com labels associadas
- [ ] Contrast ratio > 4.5:1
- [ ] Fontes legíveis (mínimo 12px)
- [ ] Sem cores como único indicador

### Responsividade
- [ ] Sem scroll horizontal (exceto tabelas)
- [ ] Elementos alinhados corretamente
- [ ] Imagens não transbordando
- [ ] Vídeos mantendo aspect ratio

### Touch-Friendly
- [ ] Hit areas > 44x44px
- [ ] Espaçamento entre botões > 8px
- [ ] Feedback visual em toque
- [ ] Sem hover-only controls

### Funcionalidade
- [ ] Links funcionando
- [ ] Forms enviando dados
- [ ] Upload funcionando
- [ ] Modals aparecendo corretamente
- [ ] QR Code gerando
- [ ] Compartilhamento funcionando

---

## 🐛 Problemas Conhecidos

Se encontrar algum dos seguintes, reporte:

- [ ] Inputs fazendo zoom ao focar
- [ ] Tabelas quebrando o layout
- [ ] Botões inacessíveis por tamanho
- [ ] Delay ao tocar em botões
- [ ] Formulários com submissão dupla
- [ ] Imagens distorcidas

---

## 📱 Dispositivos Recomendados para Teste

1. **iPhone SE (375x667)** - Celular pequeno
2. **iPhone 12 (390x844)** - Celular padrão
3. **iPhone 12 Pro Max (428x926)** - Celular grande
4. **iPad (768x1024)** - Tablet
5. **Android 360px** - Celular muito pequeno

---

## 💡 Dicas de Teste

### Chrome DevTools
```
1. F12 → Toggle device toolbar (Ctrl+Shift+M)
2. Selecione dispositivo específico
3. Teste interatividade em tempo real
```

### Em Dispositivo Real
```
1. Rode servidor local
2. Acesse: http://IP-LOCAL:portas/STEPS/
3. Teste tudo manualmente
```

### Emulação de Network
```
DevTools → Network tab → Throttling
Selecione "Slow 4G" para teste realista
```

---

**Status:** ✅ Todas as otimizações implementadas e testáveis
