# Task: Implement the core financial transaction logic. This requires extreme data integrity.

## Instructions:

1. Create TransactionController and TransferController.

2. Transactions Endpoint:
    - Handle optional photo uploads (store in public/transactions).
    - CRITICAL RULE: Recording a transaction MUST update the associated Wallet's balance. You MUST wrap the Transaction::create() and $wallet->update() inside a DB::transaction().

    - Logic: If TransactionType is 'income', add to wallet balance. If 'outcome' or 'saving', subtract from wallet balance.

3. Transfers Endpoint:
    - Accept from_wallet_id, to_wallet_id, and amount.
    - CRITICAL RULE: Use DB::transaction(). Deduct the amount from from_wallet and add it to to_wallet. Create a Transfer record.
    - Add validation to ensure from_wallet has sufficient balance before transferring.

4. Write rigorous Pest feature tests. Include tests that specifically simulate insufficient balances and verify that database rollbacks occur if an exception is thrown during the process.
