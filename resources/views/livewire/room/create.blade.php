<div class="w-full max-w-md">
    <div class="bg-gray-800/50 backdrop-blur rounded-2xl shadow-xl p-8">
        <h1 class="text-3xl font-bold text-center mb-8">Create a Room</h1>

        <form wire:submit="create" class="space-y-6">
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

            <div>
                <label for="packId" class="block text-sm font-medium text-gray-300 mb-2">
                    Choose a Pack
                </label>
                <div class="space-y-2">
                    @foreach ($packs as $pack)
                        <label class="flex items-center p-4 rounded-lg cursor-pointer transition-all {{ $packId == $pack->id ? 'bg-purple-600 ring-2 ring-purple-400 ring-offset-2 ring-offset-gray-800' : 'bg-gray-700/30 hover:bg-gray-700/50' }}">
                            <input
                                wire:model.live="packId"
                                type="radio"
                                name="packId"
                                value="{{ $pack->id }}"
                                class="sr-only"
                            >
                            <div class="flex-1">
                                <div class="font-medium {{ $packId == $pack->id ? 'text-white' : '' }}">{{ $pack->name }}</div>
                                <div class="text-sm {{ $packId == $pack->id ? 'text-purple-200' : 'text-gray-400' }}">{{ $pack->tracks_count }} tracks</div>
                            </div>
                            @if ($packId == $pack->id)
                                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            @endif
                        </label>
                    @endforeach
                </div>
                @error('packId')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <button
                type="submit"
                class="w-full py-4 px-6 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove>Create Room</span>
                <span wire:loading>Creating...</span>
            </button>
        </form>

        <div class="mt-6 text-center">
            <a href="{{ route('room.join') }}" class="text-purple-400 hover:text-purple-300 transition">
                Or join an existing room
            </a>
        </div>
    </div>
</div>
