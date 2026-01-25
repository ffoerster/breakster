<div class="w-full max-w-md">
    <div class="bg-gray-800/50 backdrop-blur rounded-2xl shadow-xl p-8">
        <h1 class="text-3xl font-bold text-center mb-8">Join a Room</h1>

        <form wire:submit="join" class="space-y-6">
            <div>
                <label for="code" class="block text-sm font-medium text-gray-300 mb-2">
                    Room Code
                </label>
                <input
                    wire:model="code"
                    type="text"
                    id="code"
                    class="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-white placeholder-gray-400 text-center text-2xl tracking-widest uppercase"
                    placeholder="ABC123"
                    maxlength="8"
                    required
                >
                @error('code')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="displayName" class="block text-sm font-medium text-gray-300 mb-2">
                    Your Name
                </label>
                <input
                    wire:model="displayName"
                    type="text"
                    id="displayName"
                    class="w-full px-4 py-3 bg-gray-700/50 border border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-white placeholder-gray-400"
                    placeholder="Enter your display name"
                    maxlength="30"
                    required
                >
                @error('displayName')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <button
                type="submit"
                class="w-full py-4 px-6 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove>Join Room</span>
                <span wire:loading>Joining...</span>
            </button>
        </form>

        <div class="mt-6 text-center">
            <a href="{{ route('room.create') }}" class="text-purple-400 hover:text-purple-300 transition">
                Or create a new room
            </a>
        </div>
    </div>
</div>
