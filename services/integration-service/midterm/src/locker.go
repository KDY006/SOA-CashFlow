package main

import "sync"

// keyedLocker khóa theo từng key (ở đây key là tài khoản ngân hàng).
// 2 request cùng tài khoản phải xếp hàng, khác tài khoản thì chạy song song bình thường.
type keyedLocker struct {
	mu    sync.Mutex
	locks map[string]*keyLock
}

type keyLock struct {
	mu   sync.Mutex
	refs int // số goroutine đang giữ hoặc đang chờ key này
}

func newKeyedLocker() *keyedLocker {
	return &keyedLocker{locks: make(map[string]*keyLock)}
}

// Lock trả về hàm unlock, gọi bằng defer
func (k *keyedLocker) Lock(key string) func() {
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
		// không còn ai dùng thì xóa khỏi map, tránh map phình to theo số tài khoản
		if l.refs == 0 {
			delete(k.locks, key)
		}
		k.mu.Unlock()
	}
}

func (k *keyedLocker) size() int {
	k.mu.Lock()
	defer k.mu.Unlock()
	return len(k.locks)
}
