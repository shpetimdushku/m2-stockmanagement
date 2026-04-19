# Magento 2.4.7 - Stock Management Exercise

Questo repository contiene lo sviluppo dell'esercizio tecnico relativo a un modulo custom Magento 2.4.7 per la gestione della quantità in arrivo dei prodotti.

## Traccia esercizio

Un sito e-commerce in Magento 2.4.7 sta utilizzando una funzionalità di un modulo per aggiornare la quantità in arrivo (`incoming_qty`) di ogni prodotto: qualora un prodotto andasse in esaurimento, se la quantità nella tabella `incoming_stock` è > 0 allora comparirà il bottone **"avvisami quando torna disponibile"** nella scheda prodotto.

I canali di vendita che possono aggiornare questa quantità sono 3 e lo fanno tramite l'url `http://myhost.local/stockmanagement/incoming` passando in GET `entity_id` del prodotto e la quantità da aggiornare.

Il modulo in questione è in allegato, ma purtroppo ha diversi problemi.

Si richiede di:

- sistemare il modulo con quante più best practices possibili conosciute mantenendo intatte le funzionalità, se lo si ritiene cambiare anche metodologia di aggiornamento (url, metodo, approccio ecc.)
- analizzare e risolvere una delle segnalazioni più ricorrenti ricevute su questo sviluppo: risulta che a fronte di aggiornamenti massivi dai canali di vendita in orari comuni `incoming_qty` di un prodotto risulta aggiornato in maniera erronea
- (nice to have) utilizzare il campo `incoming_qty` della tabella stock per far apparire il bottone **"avvisami quando torna disponibile"** nella scheda prodotto qualora dovesse risultare out of stock ma con quantità nella tabella `incoming_stock` > 0

------------------------------------------------------------------------------------------------------------------------------

## Problemi individuati nel modulo originale

### 1. Uso diretto del ObjectManager nel controller
Nel controller originale si utilizza direttamente "ObjectManager", che non e consigliata in Magento 2 perche rende il codice piu difficile da testare, manutenere e comprendere. Si deve usare dependency injection.

### 2. Utilizzo di una route frontend con metodo GET per aggiornare
L' aggiornamento della incoming quantita si fa tramite una URL frontend e parametri GET. Questa cosa non e ideale e non e consigliato perche il metodo GET dovrebbe essere usato per operazioni di lettura [READ], non di modifica [UPDATE], e non e la miglior schelta per un integrazione tra sistemi [Third Parts Systems]. La soluzione migliore per integrazione del sito magento2 con altri sistemi e di utilizzare REST API Magento 2 e gli suoi endpoint nativi. E molto piu sicuro con Autenticazione [Authentication with Integration Token] ed e un standard 100% del Magento... 

### 3. Non esiste validazione dei parametri
I parametri "entity_id" e "incoming_qty" vengono letti direttamente dalla request senza alcun controllo, formato o validazione del contenuto o dei parametri, Non esiste nessun controllo che il prodotto esiste o no etc.

### 4. Manca autenticazione o protezione del endpoint
L'endpoint originale puo essere richiamato senza alcun meccanismo di autenticazione o autorizzazione, esponendo la logica di aggiornamento a modifiche non controllate.

------------------------------------------------------------------------------------------------------------------------------

### Aggiunta unit test
Sono stati aggiunti unit test per verificare il incoming_qty

In particolare, i test coprono i seguenti scenari:
- prodotto disponibile: HTML invariato
- prodotto non disponibile ma con incoming_qty > 0: aggiunta del bottone
- prodotto non disponibile e senza incoming_qty: HTML invariato
- prodotto nullo: HTML invariato
- gestione eccezioni: ritorno dell'HTML originale senza errori lato frontend

