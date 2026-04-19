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
