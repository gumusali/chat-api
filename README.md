# chat-api

@POST /login
@param credentials=json

>credentials={"username": "janedoe", "password": "123456"}

success:

>{"status":"success","token":"2ffbcd7f48a86cfeb6e46249c2d28aeagtn63a42bc0cf5704b5b5f6c50731a8ad"}

error:

>{"status":"error","text":"Invalid username or password"}

@Post /sendMessage
@param params=json

>params={"token": "2ffbcd7f48a86cfeb6e46249c2d28sac6ef63a42bc0cf5704b5b5f6c50731a8ad", "to": 1, "message": "Hello!"}

success:

>{"status":"success","lastInsertId":"2"}

@Get /getMessages
@param token


```
{
    "chats": [
        {
            "id": "1",
            "user1": "1",
            "user2": "2",
            "messages": "[{\"id\":1,\"chat_id\":1,\"user_from\":1,\"user_to\":2,\"message\":\"Hi!\"},{\"id\":2,\"chat_id\":1,\"user_from\":2,\"user_to\":1,\"message\":\"Hello!\"}]"
        }
    ],
    "users": [
        {
            "username": "johndoe",
            "id": "1"
        },
        {
            "username": "janedoe",
            "id": "2"
        }
    ]
}
```


Users:

johndoe:12345

janedoe:123456
