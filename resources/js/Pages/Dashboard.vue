<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import { computed } from 'vue'
import { ref } from 'vue'
import { onMounted } from 'vue'

const props = defineProps(['authUser', 'users'])

const messageContainer = ref()
const chatMessages = ref([])
const newMessage = ref('')

const receiver = ref({})
const userList = ref([...props.users])

onMounted(() => {
    if (userList.value.length) {
        setReceiverTo(userList.value[0])
    }
})

const scrollToLastMessage = () => {
    setTimeout(() => {
        messageContainer.value?.lastElementChild?.scrollIntoView({
            behavior: 'smooth',
        })
    }, 100)
}

const addMessage = (message) => {
    if (message.sender_id == receiver.value.id) {
        chatMessages.value = chatMessages.value.push(message)
        // scrollToLastMessage()
    }
}

const sendMessage = () => {
    if (!newMessage.value.length) {
        return;
    }
    axios.post('/send-message', {
        message: newMessage.value,
        receiver_id: receiver.value.id
    })
        .then(res => {

            addMessage({
                sender_id: props.authUser.id,
                receiver_id: receiver.value.id,
                message: newMessage.value
            })

            newMessage.value = ''
        }).catch(error => {
            console.log(error.response?.data);
            alert(error.response?.data?.message ?? 'Something went wrong')
        })
}

const setReceiverTo = (user) => {

    if (user?.id != receiver.value?.id) {
        receiver.value = user
        fetchOldMessages(user.id)
    }
}

const fetchOldMessages = (id) => {
    chatMessages.value = []
    axios.get(`/get-messages/${id}`)
        .then(res => {
            chatMessages.value = res.data
            scrollToLastMessage()
        })
}

const makeUserActive = (id) => {

    userList.value = userList.value.map(user => {
        if (user.id === id) {
            user.is_online = true
        }
        return user
    })

    console.log('Joined:', id)
}

const makeUserInactive = (id) => {
    userList.value = userList.value.map(user => {
        if (user.id === id) {
            user.is_online = false
        }
        return user
    })
    console.log('Leaved:', id)
}

Echo.join('online')
    .here((users) => {
        users.forEach(user => makeUserActive(user.id))
    })
    .joining((user) => {
        makeUserActive(user.id)
    })
    .leaving((user) => {
        makeUserInactive(user.id)
    })
    // .listen('NewMessage', (res) => {
    //     console.log('New Message:', res.message)
    // })
    .error((error) => {
        console.error(error);
    });

Echo.private(`chat.${props.authUser.id}`)
    .listen('NewMessage', (res) => {
        // console.log('New Message for me only:', res.message)
        addMessage(res.message)
    })

</script>

<template>
    <Head title="Dashboard" />


    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>
        </template>

        {{ chatMessages }}
        <div class="mx-auto max-w-7xl">
            <div class="grid grid-cols-4">
                <div class="col-span-1 py-12">
                    <div class="flex-1 sm:px-6 lg:px-8">
                        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div class="h-96">
                                <p class="py-2 mb-2 text-lg font-bold text-center border border-b-1">Users List</p>
                                <ul class="flex flex-col">
                                    <li v-for="user in userList" @click="setReceiverTo(user)"
                                        class="p-3 bg-gray-200 border-indigo-400 cursor-pointer hover:border-b-2"
                                        :class="{ 'border-b-2': user.id == receiver.id }">
                                        <span>{{ user.name }}</span>
                                        <span :class="[user.is_online ? 'text-green-600' : 'text-red-600']">
                                            ({{ user.is_online ? 'Online' : 'Offline' }})
                                        </span>
                                        <!-- <span>({{ user.messages?.length ?? '...' }})</span> -->
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-span-3 py-12">
                    <div class="flex-1 sm:px-6 lg:px-8">
                        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">

                            <div v-if="receiver.id" class="flex flex-col justify-between flex-1 p:2 sm:p-6 h-96">
                                <div class="pb-2 mb-3 text-2xl border-b-2">
                                    <span class="mr-3 text-gray-700">{{ receiver.name }}</span>
                                </div>
                                <div id="messages" ref="messageContainer"
                                    class="flex flex-col p-3 space-y-4 overflow-y-auto scrolling-touch scrollbar-thumb-blue scrollbar-thumb-rounded scrollbar-track-blue-lighter scrollbar-w-2">
                                    <!-- other -->
                                    <template v-for="(item, index) in chatMessages" :key="index">
                                        <div class="chat-message" v-if="item.sender_id != authUser.id">
                                            <div>
                                                <span
                                                    class="inline-block px-4 py-2 text-gray-600 bg-gray-300 rounded-lg rounded-bl-none">
                                                    {{ item.message }}
                                                </span>
                                            </div>
                                        </div>

                                        <!-- me -->
                                        <div class="chat-message" v-else>
                                            <div class="flex items-end justify-end">
                                                <div><span
                                                        class="inline-block px-4 py-2 text-white bg-blue-600 rounded-lg rounded-br-none ">
                                                        {{ item.message }}
                                                    </span></div>
                                            </div>
                                        </div>
                                    </template>

                                </div>
                                <div class="px-4 pt-4 mb-2 border-t-2 border-gray-200 sm:mb-0">
                                    <form @submit.prevent="sendMessage">
                                        <div class="relative flex">
                                            <span class="absolute inset-y-0 flex items-center">
                                                <button type="button"
                                                    class="inline-flex items-center justify-center w-12 h-12 text-gray-500 transition duration-500 ease-in-out rounded-full hover:bg-gray-300 focus:outline-none">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                        stroke="currentColor" class="w-6 h-6 text-gray-600">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z">
                                                        </path>
                                                    </svg>
                                                </button>
                                            </span>
                                            <input type="text" v-model="newMessage" placeholder="Write your message!"
                                                class="w-full py-3 pl-12 text-gray-600 placeholder-gray-600 bg-gray-200 rounded-md focus:outline-none focus:placeholder-gray-400">

                                            <div class="absolute inset-y-0 right-0 items-center hidden sm:flex">
                                                <button type="button"
                                                    class="inline-flex items-center justify-center w-10 h-10 text-gray-500 transition duration-500 ease-in-out rounded-full hover:bg-gray-300 focus:outline-none">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                        stroke="currentColor" class="w-6 h-6 text-gray-600">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13">
                                                        </path>
                                                    </svg>
                                                </button>
                                                <button type="button"
                                                    class="inline-flex items-center justify-center w-10 h-10 text-gray-500 transition duration-500 ease-in-out rounded-full hover:bg-gray-300 focus:outline-none">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                        stroke="currentColor" class="w-6 h-6 text-gray-600">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z">
                                                        </path>
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    </svg>
                                                </button>
                                                <button type="button"
                                                    class="inline-flex items-center justify-center w-10 h-10 text-gray-500 transition duration-500 ease-in-out rounded-full hover:bg-gray-300 focus:outline-none">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                        stroke="currentColor" class="w-6 h-6 text-gray-600">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                                                        </path>
                                                    </svg>
                                                </button>
                                                <button type="submit"
                                                    class="inline-flex items-center justify-center px-4 py-3 text-white transition duration-500 ease-in-out bg-blue-500 rounded-lg hover:bg-blue-400 focus:outline-none">
                                                    <span class="font-bold">Send</span>
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                                        fill="currentColor" class="w-6 h-6 ml-2 transform rotate-90">
                                                        <path
                                                            d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z">
                                                        </path>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div v-else class="flex items-center justify-center font-bold h-72">Select an user to continue
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
.scrollbar-w-2::-webkit-scrollbar {
    width: 0.25rem;
    height: 0.25rem;
}

.scrollbar-track-blue-lighter::-webkit-scrollbar-track {
    --bg-opacity: 1;
    background-color: #f7fafc;
    background-color: rgba(247, 250, 252, var(--bg-opacity));
}

.scrollbar-thumb-blue::-webkit-scrollbar-thumb {
    --bg-opacity: 1;
    background-color: #edf2f7;
    background-color: rgba(237, 242, 247, var(--bg-opacity));
}

.scrollbar-thumb-rounded::-webkit-scrollbar-thumb {
    border-radius: 0.25rem;
}
</style>