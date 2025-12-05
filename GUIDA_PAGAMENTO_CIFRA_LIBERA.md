# 💰 Guida Utente: Pagamento Cifra Libera

## 📌 Cos'è il Pagamento Cifra Libera?

Il **Pagamento Cifra Libera** è una funzionalità che permette di distribuire un importo totale (ad esempio un bonifico ricevuto) tra più scadenze dello stesso contratto, in modo flessibile e controllato.

---

## 🎯 Quando Usarla?

Usa questa funzionalità quando:
- ✅ Ricevi un bonifico da un cliente e devi distribuirlo tra più rate
- ✅ Vuoi pagare parzialmente più scadenze con un unico importo
- ✅ Hai un importo fisso da distribuire tra varie scadenze

---

## 📖 Come Funziona - Passo per Passo

### 1️⃣ **Accesso alla Funzionalità**

1. Vai alla pagina **Scadenze** (`/scadenze`)
2. Trova una scadenza **non pagata** del contratto che ti interessa
3. Clicca sul pulsante **"💰 Cifra Libera"** nella colonna Azioni

![Esempio pulsante](nella tabella scadenze, colonna destra)

---

### 2️⃣ **Inserimento Importo Totale**

Nella pagina che si apre:

1. **Inserisci l'importo totale** da distribuire nel campo in alto
   - Esempio: `5000.00` se hai ricevuto 5.000€

2. **Visualizzazione Real-time**:
   - Vedrai il totale distribuito
   - Vedrai il residuo (deve essere 0 per confermare)

---

### 3️⃣ **Selezione Scadenze**

Nella tabella vedrai **tutte le scadenze non pagate** del contratto:

#### Opzione A: Selezione Manuale
1. Spunta il checkbox delle scadenze che vuoi pagare
2. Per ogni scadenza selezionata, inserisci l'importo da pagare
3. L'importo può essere:
   - **Totale**: paga completamente la scadenza
   - **Parziale**: paga solo una parte (verrà creata una nuova scadenza per il resto)

#### Opzione B: Distribuzione Automatica 🚀
1. Spunta le scadenze che vuoi pagare
2. **Doppio-click** sul campo "Importo Totale"
3. Il sistema distribuirà automaticamente l'importo in modo proporzionale

---

### 4️⃣ **Esempio Pratico**

**Situazione:**
- Ricevi bonifico di **5.000€**
- Contratto ha 3 scadenze non pagate:
  - Rata 1: 2.000€ (scaduta)
  - Rata 2: 2.000€
  - Rata 3: 3.000€

**Operazione:**

| Scadenza | Importo Dovuto | Importo da Pagare | Risultato |
|----------|---------------|-------------------|-----------|
| ✅ Rata 1 | 2.000€ | 2.000€ | Pagata completamente |
| ✅ Rata 2 | 2.000€ | 2.000€ | Pagata completamente |
| ✅ Rata 3 | 3.000€ | 1.000€ | Pagata parzialmente |

**Dopo il pagamento:**
- Rata 1: ✅ Pagata
- Rata 2: ✅ Pagata  
- Rata 3: ✅ Pagata (1.000€)
- **Nuova Scadenza**: Rata 3 - 2.000€ (rimanenza)

---

### 5️⃣ **Data Nuove Scadenze**

Quando paghi parzialmente una scadenza:
- Il sistema **suggerisce** la data: giorno dopo l'ultima scadenza del contratto
- Puoi **modificare** questa data se necessario
- Questa data si applica a **tutte** le nuove scadenze create

---

## ⚙️ Caratteristiche Intelligenti

### ✨ Evidenziazione Visiva
- 🔵 **Righe blu**: Scadenze selezionate
- 🔴 **Righe rosse**: Scadenze scadute
- ⚪ **Righe bianche**: Scadenze normali

### 🔒 Controlli di Sicurezza
- Il pulsante "Conferma Pagamento" si attiva solo quando:
  - ✅ Residuo = 0 (tutto distribuito)
  - ✅ Almeno una scadenza selezionata
  - ✅ Importi validi per ogni scadenza

### 📊 Feedback Real-time
- Calcolo automatico del residuo
- Validazione istantanea
- Messaggi di errore chiari

---

## ❓ Domande Frequenti

### Q: Posso distribuire solo parte dell'importo totale?
**R:** No, devi distribuire tutto l'importo inserito. Il residuo deve essere 0.

### Q: Cosa succede se sbaglio?
**R:** Puoi sempre tornare indietro e ricominciare. Oppure, se hai già confermato, puoi "Annullare il Pagamento" dalla pagina Scadenze.

### Q: Posso pagare scadenze di contratti diversi?
**R:** No, il pagamento cifra libera funziona solo per scadenze dello **stesso contratto**.

### Q: Come funziona la distribuzione automatica?
**R:** Il sistema calcola la proporzione di ogni scadenza sul totale dovuto e distribuisce l'importo di conseguenza.

---

## 🚨 Casi Particolari

### Pagamento Totale di Tutte le Scadenze
Se l'importo totale è **maggiore o uguale** alla somma di tutte le scadenze:
- Seleziona tutte le scadenze
- Doppio-click sull'importo totale
- Il sistema pagherà completamente tutte le scadenze

### Pagamento Parziale Multiplo
Se vuoi pagare parzialmente più scadenze:
- Seleziona le scadenze
- Inserisci manualmente gli importi
- Verifica che il totale corrisponda

---

## 💡 Suggerimenti

1. **Priorità alle scadute**: Inizia pagando le scadenze scadute (evidenziate in rosso)
2. **Usa la distribuzione automatica**: Per una distribuzione equa e veloce
3. **Controlla sempre il residuo**: Prima di confermare, assicurati che sia 0
4. **Modifica la data se necessario**: La data suggerita può essere cambiata

---

## 📝 Riepilogo Rapido

1. Vai su `/scadenze`
2. Clicca **"💰 Cifra Libera"** su una scadenza del contratto
3. Inserisci **importo totale**
4. Seleziona **scadenze** e inserisci **importi**
5. Verifica che **residuo = 0**
6. Clicca **"Conferma Pagamento"**

✅ **Fatto!**

---

## 🆘 Supporto

In caso di problemi o dubbi, contatta l'amministratore del sistema.

---

**Ultima modifica:** Dicembre 2025

