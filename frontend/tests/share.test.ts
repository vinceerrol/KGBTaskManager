import test from 'node:test'
import assert from 'node:assert/strict'
import { buildShareMessage, taskLink, isLocalOnlyUrl, messengerTarget } from '../src/utils/share.ts'

const details = { title: 'Edit 3 product videos', description: 'Use the brand guide.\nDeliver in 9:16.', assignees: 'Carlo Mendoza, Anna Santos', team: 'AI Video Editors', priority: 'urgent', status: 'In progress', due: 'Oct 6, 5:00 PM (UTC+8)', url: 'http://192.168.1.20:5173/tasks/12' }

test('the message carries the title, instructions, people, timing and the task link', () => {
  const message = buildShareMessage(details)
  assert.match(message, /^📌 Edit 3 product videos\n\nUse the brand guide\.\nDeliver in 9:16\.\n/)
  assert.match(message, /Assigned to: Carlo Mendoza, Anna Santos/)
  assert.match(message, /Team: AI Video Editors/)
  assert.match(message, /Priority: Urgent/)
  assert.match(message, /Due: Oct 6, 5:00 PM \(UTC\+8\)/)
  assert.ok(message.endsWith('🔗 Open the task: http://192.168.1.20:5173/tasks/12'))
})

test('a missing description leaves no empty instructions block', () => {
  const message = buildShareMessage({ ...details, description: '   ' })
  assert.match(message, /^📌 Edit 3 product videos\n\n👤 Assigned to/)
})

test('very long instructions are shortened so the message stays pasteable', () => {
  const message = buildShareMessage({ ...details, description: 'x'.repeat(2000) })
  assert.ok(message.includes('x'.repeat(600) + '…'))
  assert.ok(!message.includes('x'.repeat(601)))
})

test('a configured public address wins over the current browser address', () => {
  assert.equal(taskLink(12, 'http://localhost:5173', 'http://192.168.1.20:5173/'), 'http://192.168.1.20:5173/tasks/12')
  assert.equal(taskLink(12, 'http://localhost:5173', ''), 'http://localhost:5173/tasks/12')
  assert.equal(taskLink(12, 'https://tasks.example.com/', undefined), 'https://tasks.example.com/tasks/12')
})

test('localhost links are recognised as working on this computer only', () => {
  assert.equal(isLocalOnlyUrl('http://localhost:5173/tasks/1'), true)
  assert.equal(isLocalOnlyUrl('http://127.0.0.1:5173/tasks/1'), true)
  assert.equal(isLocalOnlyUrl('http://192.168.1.20:5173/tasks/1'), false)
  assert.equal(isLocalOnlyUrl('https://tasks.example.com/tasks/1'), false)
})

test('phones get Messenger\'s send-to picker with the link; computers get Messenger on the web', () => {
  const phone = messengerTarget('Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)', 'http://192.168.1.20:5173/tasks/12')
  assert.deepEqual(phone, { kind: 'app', href: 'fb-messenger://share?link=http%3A%2F%2F192.168.1.20%3A5173%2Ftasks%2F12' })
  assert.equal(messengerTarget('Mozilla/5.0 (Linux; Android 15; Pixel 9)', 'x').kind, 'app')
  assert.deepEqual(messengerTarget('Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/140', 'x'), { kind: 'web', href: 'https://www.messenger.com/' })
})
