def funcA(v):
  return funcB(v)

def funcB(v):
  return (v + 1)

if __name__ == '__main__':
  print(funcA(1))