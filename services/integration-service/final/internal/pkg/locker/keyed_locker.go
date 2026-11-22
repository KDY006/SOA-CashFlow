package locker

import "sync"

// KeyedLocker synchronizes concurrent requests targeting the same account key
// while letting different accounts run in parallel.
type KeyedLocker struct {
	mu    sync.Mutex
	locks map[string]*keyLock
}

type keyLock struct {
	mu   sync.Mutex
	refs int
}

func New() *KeyedLocker {
	return &KeyedLocker{locks: make(map[string]*keyLock)}
}

func (k *KeyedLocker) Lock(key string) func() {
	k.mu.Lock()
	l, ok := k.locks[key]
	if !ok {
		l = &keyLock{}
		k.locks[key] = l
	}
	l.refs++
	k.mu.Unlock()

	l.mu.Lock()

	return func() {
		l.mu.Unlock()

		k.mu.Lock()
		l.refs--
		if l.refs == 0 {
			delete(k.locks, key)
		}
		k.mu.Unlock()
	}
}

func (k *KeyedLocker) Size() int {
	k.mu.Lock()
	defer k.mu.Unlock()
	return len(k.locks)
}
