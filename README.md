# BlockchainAPI

Unfortunately, I did not have a lot of time to improve the code, but I might have tried using curl instead of file_get_contents for more control on how I use the external API.
Also, I should not tried creating DB for value as it all could be calculated after getting the price by USD and added to JSON.
On the other hand, it might be better to get that information yourself and add it to DB and call after that so that users would not make a lot of calls from the external API as it would give us an error of getting blocked due to the amount of request from that API.
But unfortunatelly, I did not do that eigher it only adds to DB after I create new wallet.
I did not use any framework is it is basically just PHP.

Regarding the API, I have implemented the sessions and added the option to add new user.
Access Token used for authentication and refresh token to get new refresh and access tokens.

