#!/bin/bash
mongodbHost="${MONGO}"
rs=${RS}
port=${PORT:-27017}

echo "Waiting for startup.."
until mongo --host ${mongodbHost}:${port} --eval 'quit(db.runCommand({ ping: 1 }).ok ? 0 : 2)' &>/dev/null; do
  printf '.'
  sleep 1
done

echo "Started.."

echo setup.sh time now: `date +"%T" `
mongosh --host ${mongodbHost}:${port} <<EOF
   var cfg = {
        "_id": rs,
        "protocolVersion": 1,
        "members": [
            {
                "_id": 0,
                "host": "${mongodbHost}:${port}"
            },
        ]
    };
    rs.initiate(cfg, { force: true });
EOF
